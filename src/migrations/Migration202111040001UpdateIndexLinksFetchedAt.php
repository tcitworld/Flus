<?php

namespace flusio\migrations;

class Migration202111040001UpdateIndexLinksFetchedAt
{
    public function migrate()
    {
        $database = \Minz\Database::get();

        $database->exec(<<<'SQL'
            DROP INDEX idx_links_fetched_at;
            CREATE INDEX idx_links_fetched_at ON links(fetched_at) WHERE fetched_at IS NULL;
        SQL);

        return true;
    }

    public function rollback()
    {
        $database = \Minz\Database::get();

        $database->exec(<<<'SQL'
            DROP INDEX idx_links_fetched_at;
            CREATE INDEX idx_links_fetched_at ON links(fetched_at);
        SQL);

        return true;
    }
}
