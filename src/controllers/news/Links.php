<?php

namespace flusio\controllers\news;

use Minz\Response;
use flusio\auth;
use flusio\models;
use flusio\utils;

/**
 * @author  Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
class Links
{
    /**
     * Allow to add a link from a news_link (which is mark as read). If a link
     * already exists with the same URL, it is offered to update it.
     *
     * @request_param string id
     *
     * @response 302 /login?redirect_to=/news/:id/add
     *     if not connected
     * @response 404
     *     if the link doesn't exist, or is not associated to the current user
     * @response 200
     *     on success
     */
    public function new($request)
    {
        $user = auth\CurrentUser::get();
        $news_link_id = $request->param('id');

        if (!$user) {
            return Response::redirect('login', [
                'redirect_to' => \Minz\Url::for('add news', ['id' => $news_link_id]),
            ]);
        }

        $news_link = models\NewsLink::find($news_link_id);
        if (!auth\NewsLinksAccess::canUpdate($user, $news_link)) {
            return Response::notFound('not_found.phtml');
        }

        $collections = $user->collections(true);
        models\Collection::sort($collections, $user->locale);

        $existing_link = models\Link::findBy([
            'url' => $news_link->url,
            'user_id' => $user->id,
        ]);
        if ($existing_link) {
            $is_hidden = $existing_link->is_hidden;
            $existing_collections = $existing_link->collections();
            $collection_ids = array_column($existing_collections, 'id');
        } else {
            $is_hidden = false;
            $collection_ids = [];
        }

        return Response::ok('news/links/new.phtml', [
            'news_link' => $news_link,
            'is_hidden' => $is_hidden,
            'collection_ids' => $collection_ids,
            'collections' => $collections,
            'comment' => '',
            'exists_already' => $existing_link !== null,
        ]);
    }

    /**
     * Mark a news_link as read and add it as a link to the user's collections.
     *
     * @request_param string id
     * @request_param string csrf
     * @request_param boolean is_hidden
     * @request_param string[] collection_ids
     * @request_param string comment
     *
     * @response 302 /login?redirect_to=/news/:id/add
     *     if not connected
     * @response 404
     *     if the link doesn't exist, or is not associated to the current user
     * @response 400
     *     if CSRF is invalid, if collection_ids is empty or contains inexisting ids
     * @response 302 /news
     *     on success
     */
    public function create($request)
    {
        $user = auth\CurrentUser::get();
        $news_link_id = $request->param('id');

        if (!$user) {
            return Response::redirect('login', [
                'redirect_to' => \Minz\Url::for('add news', ['id' => $news_link_id]),
            ]);
        }

        $news_link = models\NewsLink::find($news_link_id);
        if (!auth\NewsLinksAccess::canUpdate($user, $news_link)) {
            return Response::notFound('not_found.phtml');
        }

        $collections = $user->collections(true);
        models\Collection::sort($collections, $user->locale);

        $existing_link = models\Link::findBy([
            'url' => $news_link->url,
            'user_id' => $user->id,
        ]);

        $is_hidden = $request->paramBoolean('is_hidden', false);
        $collection_ids = $request->paramArray('collection_ids', []);
        $comment = $request->param('comment', '');
        $csrf = $request->param('csrf');

        if (!\Minz\CSRF::validate($csrf)) {
            return Response::badRequest('news/links/new.phtml', [
                'news_link' => $news_link,
                'is_hidden' => $is_hidden,
                'collection_ids' => $collection_ids,
                'collections' => $collections,
                'comment' => $comment,
                'exists_already' => $existing_link !== null,
                'error' => _('A security verification failed: you should retry to submit the form.'),
            ]);
        }

        if (empty($collection_ids)) {
            return Response::badRequest('news/links/new.phtml', [
                'news_link' => $news_link,
                'is_hidden' => $is_hidden,
                'collection_ids' => $collection_ids,
                'collections' => $collections,
                'comment' => $comment,
                'exists_already' => $existing_link !== null,
                'errors' => [
                    'collection_ids' => _('The link must be associated to a collection.'),
                ],
            ]);
        }

        if (!models\Collection::daoCall('existForUser', $user->id, $collection_ids)) {
            return Response::badRequest('news/links/new.phtml', [
                'news_link' => $news_link,
                'is_hidden' => $is_hidden,
                'collection_ids' => $collection_ids,
                'collections' => $collections,
                'comment' => $comment,
                'exists_already' => $existing_link !== null,
                'errors' => [
                    'collection_ids' => _('One of the associated collection doesn’t exist.'),
                ],
            ]);
        }

        // First, save the link (if a Link with matching URL exists, just get
        // this link and optionally change its is_hidden status)
        if ($existing_link) {
            $link = $existing_link;
        } elseif ($news_link->link_id) {
            $associated_link = models\Link::find($news_link->link_id);
            $link = models\Link::copy($associated_link, $user->id);
        } else {
            $link = models\Link::init($news_link->url, $user->id, false);
        }
        $link->is_hidden = filter_var($is_hidden, FILTER_VALIDATE_BOOLEAN);
        $link->save();

        // Attach the link to the given collections (and potentially forget the
        // old ones)
        $links_to_collections_dao = new models\dao\LinksToCollections();
        $links_to_collections_dao->set($link->id, $collection_ids);

        // Then, if a comment has been passed, save it.
        if (trim($comment)) {
            $message = models\Message::init($user->id, $link->id, $comment);
            $message->save();
        }

        // Finally, mark the news_link as read.
        $news_link->read_at = \Minz\Time::now();
        $news_link->save();

        return Response::redirect('news');
    }

    /**
     * Mark a news link as read and remove it from bookmarks.
     *
     * @request_param string csrf
     *
     * @response 302 /login?redirect_to=/news
     *     if not connected
     * @response 302 /news
     * @flash error
     *     if CSRF is invalid
     * @response 302 /news
     *     on success
     */
    public function markAsRead($request)
    {
        $user = auth\CurrentUser::get();
        $from = \Minz\Url::for('news');
        $csrf = $request->param('csrf');

        if (!$user) {
            return Response::redirect('login', ['redirect_to' => $from]);
        }

        if (!\Minz\CSRF::validate($csrf)) {
            utils\Flash::set('error', _('A security verification failed.'));
            return Response::found($from);
        }

        $links_to_collections_dao = new models\dao\LinksToCollections();
        $read_list = $user->readList();
        $bookmarks = $user->bookmarks();
        $news = $user->news();

        foreach ($news->links() as $link) {
            $links_to_collections_dao->attach($link->id, [$read_list->id]);
            $links_to_collections_dao->detach($link->id, [$bookmarks->id, $news->id]);
        }

        return Response::found($from);
    }

    /**
     * Remove a link from news and add it to bookmarks.
     *
     * @request_param string csrf
     *
     * @response 302 /login?redirect_to=/news
     *     if not connected
     * @response 302 /news
     * @flash error
     *     if CSRF is invalid
     * @response 302 /news
     *     on success
     */
    public function readLater($request)
    {
        $user = auth\CurrentUser::get();
        $from = \Minz\Url::for('news');
        $csrf = $request->param('csrf');

        if (!$user) {
            return Response::redirect('login', ['redirect_to' => $from]);
        }

        if (!\Minz\CSRF::validate($csrf)) {
            utils\Flash::set('error', _('A security verification failed.'));
            return Response::found($from);
        }

        $links_to_collections_dao = new models\dao\LinksToCollections();
        $bookmarks = $user->bookmarks();
        $news = $user->news();

        foreach ($news->links() as $link) {
            $links_to_collections_dao->attach($link->id, [$bookmarks->id]);
            $links_to_collections_dao->detach($link->id, [$news->id]);
        }

        return Response::found($from);
    }

    /**
     * Remove a link from news and bookmarks.
     *
     * @request_param string csrf
     * @request_param string id
     *
     * @response 302 /login?redirect_to=/news
     *     if not connected
     * @response 302 /news
     * @flash error
     *     if the link doesn't exist, or is not associated to the current user
     * @response 302 /news
     * @flash error
     *     if CSRF is invalid
     * @response 302 /news
     *     on success
     */
    public function delete($request)
    {
        $user = auth\CurrentUser::get();
        $from = \Minz\Url::for('news');
        $news_link_id = $request->param('id');
        $csrf = $request->param('csrf');

        if (!$user) {
            return Response::redirect('login', ['redirect_to' => $from]);
        }

        $news_link = models\NewsLink::find($news_link_id);
        if (!auth\NewsLinksAccess::canUpdate($user, $news_link)) {
            utils\Flash::set('error', _('The link doesn’t exist.'));
            return Response::found($from);
        }

        if (!\Minz\CSRF::validate($csrf)) {
            utils\Flash::set('error', _('A security verification failed.'));
            return Response::found($from);
        }

        $links_to_collections_dao = new models\dao\LinksToCollections();

        // First, remove the link from the news.
        $news_link->removed_at = \Minz\Time::now();
        $news_link->save();

        // Then, we try to find a link with corresponding URL in order to
        // remove it from bookmarks.
        $link = models\Link::findBy([
            'url' => $news_link->url,
            'user_id' => $user->id,
        ]);
        if ($link) {
            $bookmarks = $user->bookmarks();
            $actual_collection_ids = array_column($link->collections(), 'id');
            if (in_array($bookmarks->id, $actual_collection_ids)) {
                $links_to_collections_dao->detach($link->id, [$bookmarks->id]);
            }
        }

        return Response::found($from);
    }
}
