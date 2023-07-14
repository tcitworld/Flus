<?php

namespace tests;

use flusio\auth;
use flusio\models;
use tests\factories\SessionFactory;
use tests\factories\TokenFactory;
use tests\factories\UserFactory;

/**
 * Provide login utility methods during tests.
 *
 * @author  Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
trait LoginHelper
{
    /**
     * Simulate a user who logs in. A User is created using a DatabaseFactory.
     *
     * @param array $user_values Values of the User to create (optional)
     * @param array $token_values Values of the associated Token (optional)
     * @param array $session_values Values of the associated Session (optional)
     */
    public function login(array $user_values = [], array $token_values = [], array $session_values = []): models\User
    {
        $token_values = array_merge([
            'expired_at' => \Minz\Time::fromNow(30, 'days'),
        ], $token_values);

        $token = TokenFactory::create($token_values);
        $user = UserFactory::create($user_values);

        $session_values['token'] = $token->token;
        $session_values['user_id'] = $user->id;
        SessionFactory::create($session_values);

        auth\CurrentUser::setSessionToken($token->token);
        return auth\CurrentUser::get();
    }

    /**
     * Simulate a user who logs out. It is called before each test to make sure
     * to reset the context.
     *
     * @before
     */
    public function logout(): void
    {
        auth\CurrentUser::reset();
    }
}
