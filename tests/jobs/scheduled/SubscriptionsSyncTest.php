<?php

namespace flusio\jobs\scheduled;

use flusio\models;
use flusio\services;
use tests\factories\UserFactory;

class SubscriptionsSyncTest extends \PHPUnit\Framework\TestCase
{
    use \tests\FakerHelper;
    use \tests\InitializerHelper;
    use \tests\MockHttpHelper;

    /**
     * @before
     */
    public function initializeSubscriptionConfiguration()
    {
        \Minz\Configuration::$application['subscriptions_enabled'] = true;
    }

    /**
     * @afterClass
     */
    public static function resetSubscriptionConfiguration()
    {
        \Minz\Configuration::$application['subscriptions_enabled'] = false;
    }

    public function testQueue()
    {
        $subscriptions_sync_job = new SubscriptionsSync();

        $this->assertSame('default', $subscriptions_sync_job->queue);
    }

    public function testSchedule()
    {
        $subscriptions_sync_job = new SubscriptionsSync();

        $this->assertSame('+4 hours', $subscriptions_sync_job->frequency);
    }

    public function testInstall()
    {
        \Minz\Configuration::$jobs_adapter = 'database';

        $this->assertSame(0, \Minz\Job::count());

        SubscriptionsSync::install();

        \Minz\Configuration::$jobs_adapter = 'test';

        $this->assertSame(1, \Minz\Job::count());
    }

    public function testSyncUpdatesExpiredAt()
    {
        $subscriptions_host = \Minz\Configuration::$application['subscriptions_host'];
        $subscriptions_sync_job = new SubscriptionsSync();
        $account_id = $this->fake('uuid');
        $old_expired_at = $this->fake('dateTime');
        $new_expired_at = $this->fake('dateTime');
        $subscription_api_url = "{$subscriptions_host}/api/accounts/sync";
        $this->mockHttpWithResponse($subscription_api_url, <<<TEXT
            HTTP/2 200
            Content-type: application/json

            {
                "{$account_id}": "{$new_expired_at->format(\Minz\Database\Column::DATETIME_FORMAT)}"
            }
            TEXT
        );
        $user = UserFactory::create([
            'subscription_account_id' => $account_id,
            'subscription_expired_at' => $old_expired_at,
        ]);

        $subscriptions_sync_job->perform();

        $user = $user->reload();
        $this->assertEquals($new_expired_at, $user->subscription_expired_at);
    }

    public function testSyncGetsAccountIdIfMissing()
    {
        $subscriptions_host = \Minz\Configuration::$application['subscriptions_host'];
        $subscriptions_sync_job = new SubscriptionsSync();
        $email = $this->fake('email');
        $account_id = $this->fake('uuid');
        $expired_at = $this->fake('dateTime');
        $subscription_api_url = "{$subscriptions_host}/api/account?email={$email}";
        $this->mockHttpWithResponse($subscription_api_url, <<<TEXT
            HTTP/2 200
            Content-type: application/json

            {
                "id": "{$account_id}",
                "expired_at": "{$expired_at->format(\Minz\Database\Column::DATETIME_FORMAT)}"
            }
            TEXT
        );
        $user = UserFactory::create([
            'email' => $email,
            'subscription_account_id' => null,
            'subscription_expired_at' => $this->fake('dateTime'),
            'validated_at' => $this->fake('dateTime'),
        ]);

        $subscriptions_sync_job->perform();

        $user = $user->reload();
        $this->assertSame($account_id, $user->subscription_account_id);
        $this->assertEquals($expired_at, $user->subscription_expired_at);
    }

    public function testSyncHandlesIfAccountIdFailsBeingGet()
    {
        $subscriptions_host = \Minz\Configuration::$application['subscriptions_host'];
        $subscriptions_sync_job = new SubscriptionsSync();
        $email = $this->fake('email');
        $expired_at = $this->fake('dateTime');
        $subscription_api_url = "{$subscriptions_host}/api/account?email={$email}";
        $this->mockHttpWithResponse($subscription_api_url, <<<TEXT
            HTTP/2 400
            Content-type: application/json

            {"error": "can’t get an id"}
            TEXT
        );
        $user = UserFactory::create([
            'email' => $email,
            'subscription_account_id' => null,
            'subscription_expired_at' => $expired_at,
        ]);

        $subscriptions_sync_job->perform();

        $user = $user->reload();
        $this->assertNull($user->subscription_account_id);
        $this->assertEquals($expired_at, $user->subscription_expired_at);
    }

    public function testSyncIgnoresInvalidExpiredAt()
    {
        $subscriptions_host = \Minz\Configuration::$application['subscriptions_host'];
        $subscriptions_sync_job = new SubscriptionsSync();
        $account_id = $this->fake('uuid');
        $old_expired_at = $this->fake('dateTime');
        $subscription_api_url = "{$subscriptions_host}/api/accounts/sync";
        $this->mockHttpWithResponse($subscription_api_url, <<<TEXT
            HTTP/2 200
            Content-type: application/json

            {
                "{$account_id}": "not a datetime"
            }
            TEXT
        );
        $user = UserFactory::create([
            'subscription_account_id' => $account_id,
            'subscription_expired_at' => $old_expired_at,
        ]);

        $subscriptions_sync_job->perform();

        $user = $user->reload();
        $this->assertEquals($old_expired_at, $user->subscription_expired_at);
    }

    public function testSyncIgnoresUnexpectedAccountIds()
    {
        $subscriptions_host = \Minz\Configuration::$application['subscriptions_host'];
        $subscriptions_sync_job = new SubscriptionsSync();
        $account_id_1 = $this->fake('uuid');
        // this account id is unknown to our system but returned by the API, it
        // should just be ignored.
        $account_id_2 = $this->fake('uuid');
        $old_expired_at = $this->fake('dateTime');
        $new_expired_at = $this->fake('dateTime');
        $subscription_api_url = "{$subscriptions_host}/api/accounts/sync";
        $this->mockHttpWithResponse($subscription_api_url, <<<TEXT
            HTTP/2 200
            Content-type: application/json

            {
                "{$account_id_1}": "{$new_expired_at->format(\Minz\Database\Column::DATETIME_FORMAT)}",
                "{$account_id_2}": "{$new_expired_at->format(\Minz\Database\Column::DATETIME_FORMAT)}"
            }
            TEXT
        );
        $user = UserFactory::create([
            'subscription_account_id' => $account_id_1,
            'subscription_expired_at' => $old_expired_at,
        ]);

        $subscriptions_sync_job->perform();

        $user = $user->reload();
        $this->assertEquals($new_expired_at, $user->subscription_expired_at);
    }

    public function testSyncDoesNotGetAccountIdIfNotValidated()
    {
        $subscriptions_host = \Minz\Configuration::$application['subscriptions_host'];
        $subscriptions_sync_job = new SubscriptionsSync();
        $email = $this->fake('email');
        $account_id = $this->fake('uuid');
        $expired_at = $this->fake('dateTime');
        $subscription_api_url = "{$subscriptions_host}/api/account?email={$email}";
        $this->mockHttpWithResponse($subscription_api_url, <<<TEXT
            HTTP/2 200
            Content-type: application/json

            {
                "id": "{$account_id}",
                "expired_at": "{$expired_at->format(\Minz\Database\Column::DATETIME_FORMAT)}"
            }
            TEXT
        );
        $user = UserFactory::create([
            'email' => $email,
            'subscription_account_id' => null,
            'subscription_expired_at' => $this->fake('dateTime'),
            'validated_at' => null,
        ]);

        $subscriptions_sync_job->perform();

        $user = $user->reload();
        $this->assertNull($user->subscription_account_id);
        $this->assertNotEquals($expired_at, $user->subscription_expired_at);
    }

    public function testSyncDoesNothingIfHttpIsInError()
    {
        $subscriptions_host = \Minz\Configuration::$application['subscriptions_host'];
        $subscriptions_sync_job = new SubscriptionsSync();
        $account_id = $this->fake('uuid');
        $old_expired_at = $this->fake('dateTime');
        $new_expired_at = $this->fake('dateTime');
        $subscription_api_url = "{$subscriptions_host}/api/accounts/sync";
        $this->mockHttpWithResponse($subscription_api_url, <<<TEXT
            HTTP/2 500
            Content-type: application/json

            {
                "{$account_id}": "{$new_expired_at->format(\Minz\Database\Column::DATETIME_FORMAT)}"
            }
            TEXT
        );
        $user = UserFactory::create([
            'subscription_account_id' => $account_id,
            'subscription_expired_at' => $old_expired_at,
        ]);

        $subscriptions_sync_job->perform();

        $user = $user->reload();
        $this->assertEquals($old_expired_at, $user->subscription_expired_at);
    }

    public function testSyncDoesNothingIfSubscriptionsAreDisabled()
    {
        $subscriptions_sync_job = new SubscriptionsSync();
        \Minz\Configuration::$application['subscriptions_enabled'] = false;
        $subscriptions_host = \Minz\Configuration::$application['subscriptions_host'];
        $subscriptions_sync_job = new SubscriptionsSync();
        $account_id = $this->fake('uuid');
        $old_expired_at = $this->fake('dateTime');
        $new_expired_at = $this->fake('dateTime');
        $subscription_api_url = "{$subscriptions_host}/api/accounts/sync";
        $this->mockHttpWithResponse($subscription_api_url, <<<TEXT
            HTTP/2 200
            Content-type: application/json

            {
                "{$account_id}": "{$new_expired_at->format(\Minz\Database\Column::DATETIME_FORMAT)}"
            }
            TEXT
        );
        $user = UserFactory::create([
            'subscription_account_id' => $account_id,
            'subscription_expired_at' => $old_expired_at,
        ]);

        $subscriptions_sync_job->perform();

        \Minz\Configuration::$application['subscriptions_enabled'] = true;

        $user = $user->reload();
        $this->assertEquals($old_expired_at, $user->subscription_expired_at);
    }
}
