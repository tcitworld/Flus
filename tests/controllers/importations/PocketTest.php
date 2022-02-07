<?php

namespace flusio\controllers\importations;

use flusio\models;
use flusio\services;
use flusio\utils;

class PocketTest extends \PHPUnit\Framework\TestCase
{
    use \tests\FakerHelper;
    use \tests\FlashAsserts;
    use \tests\InitializerHelper;
    use \tests\LoginHelper;
    use \tests\MockHttpHelper;
    use \Minz\Tests\ApplicationHelper;
    use \Minz\Tests\FactoriesHelper;
    use \Minz\Tests\ResponseAsserts;

    /** @var string */
    private static $pocket_consumer_key;

    /**
     * @beforeClass
     */
    public static function savePocketConsumerKey()
    {
        self::$pocket_consumer_key = \Minz\Configuration::$application['pocket_consumer_key'];
    }

    /**
     * @before
     */
    public function forcePocketConsumerKey()
    {
        // because some tests disable Pocket to test 404 pages
        \Minz\Configuration::$application['pocket_consumer_key'] = self::$pocket_consumer_key;
    }

    public function testShowRendersCorrectly()
    {
        $this->login();

        $response = $this->appRun('get', '/pocket');

        $this->assertResponseCode($response, 200);
        $this->assertResponseContains($response, 'Importation from Pocket');
        $this->assertResponsePointer($response, 'importations/pocket/show.phtml');
    }

    public function testShowIfImportationIsOngoing()
    {
        $user = $this->login();
        $this->create('importation', [
            'type' => 'pocket',
            'user_id' => $user->id,
            'status' => 'ongoing',
        ]);

        $response = $this->appRun('get', '/pocket');

        $this->assertResponseCode($response, 200);
        $this->assertResponseContains($response, 'We’re importing your data from Pocket');
    }

    public function testShowIfImportationIsFinished()
    {
        $user = $this->login();
        $this->create('importation', [
            'type' => 'pocket',
            'user_id' => $user->id,
            'status' => 'finished',
        ]);

        $response = $this->appRun('get', '/pocket');

        $this->assertResponseCode($response, 200);
        $this->assertResponseContains($response, 'We’ve imported your data from Pocket.');
    }

    public function testShowIfImportationIsInError()
    {
        $user = $this->login();
        $error = $this->fake('sentence');
        $this->create('importation', [
            'type' => 'pocket',
            'user_id' => $user->id,
            'status' => 'error',
            'error' => $error,
        ]);

        $response = $this->appRun('get', '/pocket');

        $this->assertResponseCode($response, 200);
        $this->assertResponseContains($response, $error);
    }

    public function testShowRedirectsToLoginIfNotConnected()
    {
        $response = $this->appRun('get', '/pocket');

        $this->assertResponseCode($response, 302, '/login?redirect_to=%2Fpocket');
    }

    public function testShowFailsIfPocketNotConfigured()
    {
        \Minz\Configuration::$application['pocket_consumer_key'] = null;
        $this->login();

        $response = $this->appRun('get', '/pocket');

        $this->assertResponseCode($response, 404);
    }

    public function testImportRegistersAPocketImportatorJobAndRedirects()
    {
        \Minz\Configuration::$application['job_adapter'] = 'database';
        $job_dao = new models\dao\Job();
        $user = $this->login([
            'pocket_access_token' => 'some token',
        ]);

        $this->assertSame(0, models\Importation::count());
        $this->assertSame(0, $job_dao->count());

        $response = $this->appRun('post', '/pocket', [
            'csrf' => $user->csrf,
        ]);

        \Minz\Configuration::$application['job_adapter'] = 'test';

        $this->assertSame(1, models\Importation::count());
        $this->assertSame(1, $job_dao->count());

        $this->assertResponseCode($response, 302, '/pocket');
        $importation = models\Importation::take();
        $db_job = $job_dao->listAll()[0];
        $handler = json_decode($db_job['handler'], true);
        $this->assertSame('flusio\\jobs\\PocketImportator', $handler['job_class']);
        $this->assertSame([$importation->id], $handler['job_args']);
    }

    public function testImportRedirectsToLoginIfNotConnected()
    {
        \Minz\Configuration::$application['job_adapter'] = 'database';
        $job_dao = new models\dao\Job();
        $user_id = $this->create('user', [
            'csrf' => 'some token',
            'pocket_access_token' => 'some token',
        ]);

        $response = $this->appRun('post', '/pocket', [
            'csrf' => 'some token',
        ]);

        \Minz\Configuration::$application['job_adapter'] = 'test';

        $this->assertResponseCode($response, 302, '/login?redirect_to=%2Fpocket');
        $this->assertSame(0, models\Importation::count());
        $this->assertSame(0, $job_dao->count());
    }

    public function testImportFailsIfPocketNotConfigured()
    {
        \Minz\Configuration::$application['pocket_consumer_key'] = null;
        \Minz\Configuration::$application['job_adapter'] = 'database';
        $job_dao = new models\dao\Job();
        $user = $this->login([
            'pocket_access_token' => 'some token',
        ]);

        $response = $this->appRun('post', '/pocket', [
            'csrf' => $user->csrf,
        ]);

        \Minz\Configuration::$application['job_adapter'] = 'test';

        $this->assertResponseCode($response, 404);
        $this->assertSame(0, models\Importation::count());
        $this->assertSame(0, $job_dao->count());
    }

    public function testImportFailsIfUserHasNoAccessToken()
    {
        \Minz\Configuration::$application['job_adapter'] = 'database';
        $job_dao = new models\dao\Job();
        $user = $this->login([
            'pocket_access_token' => null,
        ]);

        $response = $this->appRun('post', '/pocket', [
            'csrf' => $user->csrf,
        ]);

        \Minz\Configuration::$application['job_adapter'] = 'test';

        $this->assertResponseCode($response, 400);
        $this->assertResponseContains($response, 'You didn’t authorize us to access your Pocket data');
        $this->assertSame(0, models\Importation::count());
        $this->assertSame(0, $job_dao->count());
    }

    public function testImportFailsIfCsrfIsInvalid()
    {
        \Minz\Configuration::$application['job_adapter'] = 'database';
        $job_dao = new models\dao\Job();
        $user = $this->login([
            'pocket_access_token' => 'some token',
        ]);

        $response = $this->appRun('post', '/pocket', [
            'csrf' => 'not the token',
        ]);

        \Minz\Configuration::$application['job_adapter'] = 'test';

        $this->assertResponseCode($response, 400);
        $this->assertResponseContains($response, 'A security verification failed');
        $this->assertSame(0, models\Importation::count());
        $this->assertSame(0, $job_dao->count());
    }

    public function testImportFailsIfAnImportAlreadyExists()
    {
        \Minz\Configuration::$application['job_adapter'] = 'database';
        $job_dao = new models\dao\Job();
        $user = $this->login([
            'pocket_access_token' => 'some token',
        ]);
        $this->create('importation', [
            'type' => 'pocket',
            'user_id' => $user->id,
        ]);

        $response = $this->appRun('post', '/pocket', [
            'csrf' => $user->csrf,
        ]);

        \Minz\Configuration::$application['job_adapter'] = 'test';

        $this->assertResponseCode($response, 400);
        $this->assertResponseContains($response, 'You already have an ongoing Pocket importation');
        $this->assertSame(1, models\Importation::count());
        $this->assertSame(0, $job_dao->count());
    }

    public function testRequestAccessSetRequestTokenAndRedirectsUser()
    {
        $code = $this->fake('uuid');
        $this->mockHttpWithResponse('https://getpocket.com/v3/oauth/request', <<<TEXT
            HTTP/2 200
            Content-type: application/json

            {"code": "{$code}"}
            TEXT
        );
        $user = $this->login([
            'pocket_request_token' => null,
        ]);

        $response = $this->appRun('post', '/pocket/request', [
            'csrf' => $user->csrf,
        ]);

        $user = models\User::find($user->id);
        $this->assertSame($code, $user->pocket_request_token);
        $auth_url = urlencode(\Minz\Url::absoluteFor('pocket auth'));
        $expected_url = 'https://getpocket.com/auth/authorize';
        $expected_url .= "?request_token={$user->pocket_request_token}";
        $expected_url .= "&redirect_uri={$auth_url}";
        $this->assertResponseCode($response, 302, $expected_url);
    }

    public function testRequestAccessRedirectsToLoginIfNotConnected()
    {
        $user_id = $this->create('user', [
            'csrf' => 'a token',
        ]);

        $response = $this->appRun('post', '/pocket/request', [
            'csrf' => 'a token',
        ]);

        $this->assertResponseCode($response, 302, '/login?redirect_to=%2Fpocket');
        $user = models\User::find($user_id);
        $this->assertNull($user->pocket_request_token);
    }

    public function testRequestAccessFailsIfCsrfIsInvalid()
    {
        $user = $this->login([
            'pocket_request_token' => null,
        ]);

        $response = $this->appRun('post', '/pocket/request', [
            'csrf' => 'not the token',
        ]);

        $this->assertResponseCode($response, 302, '/pocket');
        $this->assertFlash('error', 'A security verification failed.');
        $user = models\User::find($user->id);
        $this->assertNull($user->pocket_request_token);
    }

    public function testRequestAccessFailsIfPocketNotConfigured()
    {
        \Minz\Configuration::$application['pocket_consumer_key'] = null;
        $user = $this->login([
            'pocket_request_token' => null,
        ]);

        $response = $this->appRun('post', '/pocket/request', [
            'csrf' => $user->csrf,
        ]);

        $this->assertResponseCode($response, 404);
        $user = models\User::find($user->id);
        $this->assertNull($user->pocket_request_token);
    }

    public function testAuthorizationRendersCorrectly()
    {
        $this->login([
            'pocket_request_token' => 'some token',
        ]);

        $response = $this->appRun('get', '/pocket/auth');

        $this->assertResponseCode($response, 200);
        $this->assertResponseContains($response, 'Please wait while we’re verifying access to Pocket');
        $this->assertResponsePointer($response, 'importations/pocket/authorization.phtml');
    }

    public function testAuthorizationRedirectsToLoginIfNotConnected()
    {
        $response = $this->appRun('get', '/pocket/auth');

        $this->assertResponseCode($response, 302, '/login?redirect_to=%2Fpocket%2Fauth');
    }

    public function testAuthorizationRedirectsIfUserHasNoRequestToken()
    {
        $this->login([
            'pocket_request_token' => null,
        ]);

        $response = $this->appRun('get', '/pocket/auth');

        $this->assertResponseCode($response, 302, '/pocket');
    }

    public function testAuthorizationFailsIfPocketNotConfigured()
    {
        \Minz\Configuration::$application['pocket_consumer_key'] = null;
        $this->login([
            'pocket_request_token' => 'some token',
        ]);

        $response = $this->appRun('get', '/pocket/auth');

        $this->assertResponseCode($response, 404);
    }

    public function testAuthorizeRedirectsToLoginIfNotConnected()
    {
        $this->create('user', [
            'csrf' => 'some token',
        ]);

        $response = $this->appRun('post', '/pocket/auth', [
            'csrf' => 'some token',
        ]);

        $this->assertResponseCode($response, 302, '/login?redirect_to=%2Fpocket%2Fauth');
    }

    public function testAuthorizeRedirectsIfUserHasNoRequestToken()
    {
        $user = $this->login([
            'pocket_request_token' => null,
        ]);

        $response = $this->appRun('post', '/pocket/auth', [
            'csrf' => $user->csrf,
        ]);

        $this->assertResponseCode($response, 302, '/pocket');
    }

    public function testAuthorizeFailsIfPocketNotConfigured()
    {
        \Minz\Configuration::$application['pocket_consumer_key'] = null;
        $user = $this->login([
            'pocket_request_token' => 'some token',
        ]);

        $response = $this->appRun('post', '/pocket/auth', [
            'csrf' => $user->csrf,
        ]);

        $this->assertResponseCode($response, 404);
    }

    public function testAuthorizeFailsIfCsrfIsInvalid()
    {
        $user = $this->login([
            'pocket_request_token' => 'some token',
        ]);

        $response = $this->appRun('post', '/pocket/auth', [
            'csrf' => 'not the token',
        ]);

        $this->assertResponseCode($response, 400);
        $this->assertResponseContains($response, 'A security verification failed');
    }
}
