<?php

namespace flusio;

use tests\factories\SessionFactory;
use tests\factories\TokenFactory;
use tests\factories\UserFactory;

class ApplicationTest extends \PHPUnit\Framework\TestCase
{
    use \tests\FakerHelper;
    use \tests\InitializerHelper;
    use \tests\LoginHelper;
    use \Minz\Tests\ResponseAsserts;

    public function testRunSetsTheDefaultLocale()
    {
        $request = new \Minz\Request('GET', '/');

        $application = new Application();
        $response = $application->run($request);

        $variables = \Minz\Output\View::defaultVariables();
        $this->assertSame('en_GB', $variables['current_locale']);
        $this->assertSame('en_GB', utils\Locale::currentLocale());
    }

    public function testRunSetsTheLocaleFromSessionLocale()
    {
        $_SESSION['locale'] = 'fr_FR';
        $request = new \Minz\Request('GET', '/');

        $application = new Application();
        $response = $application->run($request);

        $variables = \Minz\Output\View::defaultVariables();
        $this->assertSame('fr_FR', $variables['current_locale']);
        $this->assertSame('fr_FR', utils\Locale::currentLocale());
    }

    public function testRunSetsTheLocaleFromConnectedUser()
    {
        $this->login([
            'locale' => 'fr_FR',
        ]);
        $request = new \Minz\Request('GET', '/');

        $application = new Application();
        $response = $application->run($request);

        $variables = \Minz\Output\View::defaultVariables();
        $this->assertSame('fr_FR', $variables['current_locale']);
        $this->assertSame('fr_FR', utils\Locale::currentLocale());
    }

    public function testRunSetsCurrentUserFromCookie()
    {
        $expired_at = \Minz\Time::fromNow(30, 'days');
        $token = TokenFactory::create([
            'expired_at' => $expired_at,
        ]);
        $user = UserFactory::create();
        SessionFactory::create([
            'user_id' => $user->id,
            'token' => $token->token,
        ]);

        $current_user = auth\CurrentUser::get();
        $this->assertNull($current_user);

        $request = new \Minz\Request('GET', '/', [], [
            'COOKIE' => [
                'flusio_session_token' => $token->token,
            ],
        ]);
        $application = new Application();
        $response = $application->run($request);

        $current_user = auth\CurrentUser::get();
        $this->assertSame($user->id, $current_user->id);
    }

    public function testRunSetsAutoloadModal()
    {
        $user = $this->login([
            'autoload_modal' => 'showcase navigation',
        ]);
        $request = new \Minz\Request('GET', '/news');

        $application = new Application();
        $response = $application->run($request);

        $this->assertResponseCode($response, 200);
        $this->assertResponseContains($response, \Minz\Url::for('showcase', ['id' => 'navigation']));
        $user = $user->reload();
        $this->assertEmpty($user->autoload_modal);
    }

    public function testRunDoesNotResetAutoloadModalOnRedirections()
    {
        $user = $this->login([
            'autoload_modal' => 'showcase navigation',
        ]);
        $request = new \Minz\Request('GET', '/collections');

        $application = new Application();
        $response = $application->run($request);

        $this->assertResponseCode($response, 301);
        $user = $user->reload();
        $this->assertSame('showcase navigation', $user->autoload_modal);
    }

    public function testRunRedirectsIfUserOlderThan1DaysNotValidated()
    {
        $created_at = \Minz\Time::ago($this->fake('numberBetween', 2, 42), 'days');
        $this->login([
            'created_at' => $created_at,
            'validated_at' => null,
        ]);
        $request = new \Minz\Request('GET', '/news');

        $application = new Application();
        $response = $application->run($request);

        $this->assertResponseCode($response, 302, '/my/account/validation');
    }

    public function testRunRedirectsIfUserSubscriptionIsOverdue()
    {
        \Minz\Configuration::$application['subscriptions_enabled'] = true;
        $expired_at = \Minz\Time::ago($this->fake('randomDigitNotNull'), 'days');
        $this->login([
            'subscription_expired_at' => $expired_at,
        ]);
        $request = new \Minz\Request('GET', '/news');

        $application = new Application();
        $response = $application->run($request);

        \Minz\Configuration::$application['subscriptions_enabled'] = false;

        $this->assertResponseCode($response, 302, '/my/account');
    }

    public function testRunLogoutAndRedirectsIfConnectedWithSupportUser()
    {
        $this->login([
            'email' => \Minz\Configuration::$application['support_email'],
        ]);
        $request = new \Minz\Request('GET', '/news');

        $application = new Application();
        $response = $application->run($request);

        $this->assertResponseCode($response, 302, '/login');
        $current_user = auth\CurrentUser::get();
        $this->assertNull($current_user);
    }

    public function testHeaders()
    {
        $request = new \Minz\Request('GET', '/');
        $application = new Application();

        $response = $application->run($request);

        $headers = $response->headers(true);
        $this->assertSame('interest-cohort=()', $headers['Permissions-Policy']);
        $this->assertSame('same-origin', $headers['Referrer-Policy']);
        $this->assertSame('nosniff', $headers['X-Content-Type-Options']);
        $this->assertSame('deny', $headers['X-Frame-Options']);
        $this->assertSame([
            'default-src' => "'self'",
            'style-src' => "'self' 'unsafe-inline'",
        ], $headers['Content-Security-Policy']);
    }
}
