<?php

namespace flusio\cli;

use Minz\Response;
use flusio\models;

/**
 * Display help message.
 *
 * @author  Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
class Help
{
    public function show()
    {
        $topic_label_max_size = models\Topic::LABEL_MAX_SIZE;

        $usage = "Usage: php cli COMMAND [--OPTION=VALUE]...\n";
        $usage .= "\n";
        $usage .= "COMMAND can be one of the following:\n";
        $usage .= "  help                     Show this help\n";
        $usage .= "\n";
        $usage .= "  database status          Return the status of the DB connection\n";
        $usage .= "\n";
        $usage .= "  features                 List the available features types\n";
        $usage .= "  features flags           List the enabled feature flags\n";
        $usage .= "  features enable          Enable a feature flag for a user\n";
        $usage .= "      --type=TEXT          where TEXT is the feature flag type\n";
        $usage .= "      --user_id=ID         where ID is the user’s id\n";
        $usage .= "  features disable         Disable a feature flag for a user\n";
        $usage .= "      --type=TEXT          where TEXT is the feature flag type\n";
        $usage .= "      --user_id=ID         where ID is the user’s id\n";
        $usage .= "\n";
        $usage .= "  feeds                    List the feeds\n";
        $usage .= "  feeds add                Add a feed\n";
        $usage .= "      --url=URL            where URL is the link to the feed\n";
        $usage .= "  feeds reset-hashes       Reset the hashes of all the feeds (allow sync of unchanged feeds)\n";
        $usage .= "  feeds sync               Synchronize a feed\n";
        $usage .= "      --id=ID              where ID is the id of the feed\n";
        $usage .= "      [--nocache=BOOL]     Indicates if the cache should be ignored (default is false)\n";
        $usage .= "\n";
        $usage .= "  jobs                     List the jobs\n";
        $usage .= "  jobs install             (Re-)install the jobs (to run after a configuration change)\n";
        $usage .= "  jobs run                 Execute one waiting job\n";
        $usage .= "      [--queue=TEXT]       where TEXT is one of default, mailers, importators, fetchers or all\n";
        $usage .= "  jobs unlock              Unlock a job\n";
        $usage .= "      --id=ID              where ID is a job id\n";
        $usage .= "  jobs watch               Wait and execute jobs\n";
        $usage .= "      [--queue=TEXT]       where TEXT is one of default, mailers, importators, fetchers or all\n";
        $usage .= "\n";
        $usage .= "  media clean              Clean the unused media (it may take a VERY long time to finish)\n";
        $usage .= "\n";
        $usage .= "  migrations               List the migrations\n";
        $usage .= "  migrations apply         Apply the migrations\n";
        $usage .= "  migrations create        Create a migration\n";
        $usage .= "      --name=TEXT          where TEXT is the name of the migration (e.g. CreateUsers)\n";
        $usage .= "  migrations rollback      Reverse the last migration\n";
        $usage .= "      [--steps=NUMBER]     where NUMBER is the number of rollbacks to apply (default is 1)\n";
        $usage .= "\n";
        $usage .= "  system                   Show information about the system\n";
        $usage .= "  system secret            Generate a secure key to be used as APP_SECRET_KEY\n";
        $usage .= "  system setup             Initialize or update the system\n";
        $usage .= "\n";
        $usage .= "  topics                   List the topics\n";
        $usage .= "  topics create            Create a topic\n";
        $usage .= "      --label=TEXT         where TEXT is a {$topic_label_max_size}-chars max string\n";
        $usage .= "      [--image_url=URL]    where URL is an optional illustration image\n";
        $usage .= "  topics delete            Delete a topic\n";
        $usage .= "      --id=ID              where ID is the id of the topic to delete\n";
        $usage .= "  topics update            Update a topic\n";
        $usage .= "      --id=ID              where ID is the id of the topic to delete\n";
        $usage .= "      [--label=TEXT]       where TEXT is a {$topic_label_max_size}-chars max string\n";
        $usage .= "      [--image_url=URL]    where URL is an optional illustration image\n";
        $usage .= "\n";
        $usage .= "  urls show                Show the HTTP response returned by an URL\n";
        $usage .= "      --url=TEXT           where TEXT is an external URL\n";
        $usage .= "  urls uncache             Clear the cache of the given URL\n";
        $usage .= "      --url=TEXT           where TEXT is an external URL\n";
        $usage .= "\n";
        $usage .= "  users                    List all the users\n";
        $usage .= "  users create             Create a user\n";
        $usage .= "      --email=EMAIL\n";
        $usage .= "      --password=PASSWORD\n";
        $usage .= "      --username=USERNAME  where USERNAME is a 50-chars max string\n";
        $usage .= "  users export             Export the data of the given user in the current directory\n";
        $usage .= "      --id=ID              where ID is the id of the user to export\n";
        $usage .= "  users validate           Validate a user account\n";
        $usage .= "      --id=ID              where ID is the id of the user to validate\n";

        return Response::text(200, $usage);
    }
}
