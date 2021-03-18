<?php

namespace SpiderBits\feeds;

/**
 * An Entry is a generic object to abstract Atom entries and RSS items.
 *
 * @author  Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
class Entry
{
    /** @var string */
    public $id = '';

    /** @var string */
    public $title = '';

    /** @var string */
    public $link = '';

    /** @var \DateTime */
    public $published_at = null;
}
