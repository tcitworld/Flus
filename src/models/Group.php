<?php

namespace flusio\models;

use flusio\utils;

/**
 * @author  Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
class Group extends \Minz\Model
{
    use DaoConnector;

    public const NAME_MAX_LENGTH = 100;

    public const PROPERTIES = [
        'id' => [
            'type' => 'string',
            'required' => true,
        ],

        'created_at' => [
            'type' => 'datetime',
        ],

        'name' => [
            'type' => 'string',
            'required' => true,
            'validator' => '\flusio\models\Group::validateName',
        ],

        'user_id' => [
            'type' => 'string',
            'required' => true,
        ],
    ];

    /**
     * Initialize the model with default values.
     *
     * @param mixed $values
     */
    public function __construct($values)
    {
        parent::__construct(array_merge([
            'id' => utils\Random::timebased(),
            'name' => '',
        ], $values));
    }

    /**
     * @param string $user_id
     * @param string $name
     */
    public static function init($user_id, $name)
    {
        return new self([
            'name' => trim($name),
            'user_id' => $user_id,
        ]);
    }

    /**
     * Return collections put in current group.
     *
     * @return \flusio\models\Collection[]
     */
    public function collections()
    {
        $collections = Collection::daoToList(
            'listComputedByUserId',
            $this->user_id,
            ['number_links'],
            [
                'group' => $this->id,
            ]
        );
        utils\Sorter::localeSort($collections, 'name');
        return $collections;
    }

    /**
     * Return followed collections put in current group.
     *
     * @return \flusio\models\Collection[]
     */
    public function followedCollections()
    {
        $collections = Collection::daoToList(
            'listComputedFollowedByUserId',
            $this->user_id,
            ['number_links'],
            [
                'group' => $this->id,
            ]
        );
        utils\Sorter::localeSort($collections, 'name');
        return $collections;
    }

    /**
     * @param string $name
     * @return boolean
     */
    public static function validateName($name)
    {
        return mb_strlen($name) <= self::NAME_MAX_LENGTH;
    }

    /**
     * Return a list of errors (if any). The array keys indicated the concerned
     * property.
     *
     * @return string[]
     */
    public function validate()
    {
        $formatted_errors = [];

        foreach (parent::validate() as $property => $error) {
            $code = $error['code'];

            if ($property === 'name' && $code === 'required') {
                $formatted_error = _('The name is required.');
            } elseif ($property === 'name') {
                $formatted_error = vsprintf(
                    _('The name must be less than %d characters.'),
                    self::NAME_MAX_LENGTH
                );
            } else {
                $formatted_error = $error['description']; // @codeCoverageIgnore
            }

            $formatted_errors[$property] = $formatted_error;
        }

        return $formatted_errors;
    }
}
