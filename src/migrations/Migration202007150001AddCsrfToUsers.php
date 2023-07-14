<?php

namespace flusio\migrations;

use flusio\models;

class Migration202007150001AddCsrfToUsers
{
    public function migrate()
    {
        $database = \Minz\Database::get();

        $database->exec(<<<'SQL'
            ALTER TABLE users
            ADD COLUMN csrf TEXT NOT NULL DEFAULT '';
        SQL);

        $users = models\User::listAll();
        foreach ($users as $user) {
            models\User::update($user->id, [
                'csrf' => \bin2hex(\random_bytes(32)),
            ]);
        }

        return true;
    }

    public function rollback()
    {
        $database = \Minz\Database::get();

        $database->exec(<<<'SQL'
            ALTER TABLE users
            DROP COLUMN csrf;
        SQL);

        return true;
    }
}
