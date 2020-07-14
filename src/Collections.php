<?php

namespace flusio;

use Minz\Response;

/**
 * Handle the requests related to the collections.
 *
 * @author  Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
class Collections
{
    /**
     * Show the page listing all the collections of the current user
     *
     * @response 302 /login?redirect_to=/collections if not connected
     * @response 200
     *
     * @param \Minz\Request $request
     *
     * @return \Minz\Response
     */
    public function index()
    {
        $user = utils\CurrentUser::get();
        if (!$user) {
            return Response::redirect('login', [
                'redirect_to' => \Minz\Url::for('collections'),
            ]);
        }

        $collections = $user->collectionsWithNumberLinks();
        $collator = new \Collator($user->locale);
        usort($collections, function ($collection1, $collection2) use ($collator) {
            return $collator->compare($collection1->name, $collection2->name);
        });

        return Response::ok('collections/index.phtml', [
            'collections' => $collections,
        ]);
    }

    /**
     * Show the page to create a collection
     *
     * @response 302 /login?redirect_to=/collections/new if not connected
     * @response 200
     *
     * @param \Minz\Request $request
     *
     * @return \Minz\Response
     */
    public function new()
    {
        if (!utils\CurrentUser::get()) {
            return Response::redirect('login', [
                'redirect_to' => \Minz\Url::for('new collection'),
            ]);
        }

        return Response::ok('collections/new.phtml', [
            'name' => '',
            'description' => '',
        ]);
    }

    /**
     * Create a collection
     *
     * @request_param string csrf
     * @request_param string name
     * @request_param string description
     *
     * @response 302 /login?redirect_to=/collections/new if not connected
     * @response 400 if csrf or name are invalid
     * @response 302 /collections/:new
     *
     * @param \Minz\Request $request
     *
     * @return \Minz\Response
     */
    public function create($request)
    {
        $user = utils\CurrentUser::get();
        if (!$user) {
            return Response::redirect('login', [
                'redirect_to' => \Minz\Url::for('new collection'),
            ]);
        }

        $name = $request->param('name', '');
        $description = $request->param('description', '');

        $csrf = new \Minz\CSRF();
        if (!$csrf->validateToken($request->param('csrf'))) {
            return Response::badRequest('collections/new.phtml', [
                'name' => $name,
                'description' => $description,
                'error' => _('A security verification failed: you should retry to submit the form.'),
            ]);
        }

        $collection = models\Collection::init($user->id, $name, $description);
        $errors = $collection->validate();
        if ($errors) {
            return Response::badRequest('collections/new.phtml', [
                'name' => $name,
                'description' => $description,
                'errors' => $errors,
            ]);
        }

        $collection_dao = new models\dao\Collection();
        $collection_id = $collection_dao->save($collection);

        return Response::redirect('collection', ['id' => $collection_id]);
    }

    /**
     * Show a collection page
     *
     * @request_param string id
     *
     * @response 302 /login?redirect_to=/collection/:id if not connected
     * @response 404 if the collection doesn’t exist or user hasn't access
     * @response 200
     *
     * @param \Minz\Request $request
     *
     * @return \Minz\Response
     */
    public function show($request)
    {
        $user = utils\CurrentUser::get();
        $collection_id = $request->param('id');

        if (!$user) {
            return Response::redirect('login', [
                'redirect_to' => \Minz\Url::for('collection', ['id' => $collection_id]),
            ]);
        }

        $collection = $user->collection($collection_id);
        if ($collection) {
            return Response::ok('collections/show.phtml', [
                'collection' => $collection,
                'links' => $collection->links(),
            ]);
        } else {
            return Response::notFound('not_found.phtml');
        }
    }

    /**
     * Show the edition page of a collection
     *
     * @request_param string id
     *
     * @response 302 /login?redirect_to=/collection/:id/edit if not connected
     * @response 404 if the collection doesn’t exist or user hasn't access
     * @response 200
     *
     * @param \Minz\Request $request
     *
     * @return \Minz\Response
     */
    public function edit($request)
    {
        $user = utils\CurrentUser::get();
        $collection_id = $request->param('id');

        if (!$user) {
            return Response::redirect('login', [
                'redirect_to' => \Minz\Url::for('edit collection', ['id' => $collection_id]),
            ]);
        }

        $collection = $user->collection($collection_id);
        if ($collection) {
            return Response::ok('collections/edit.phtml', [
                'collection' => $collection,
                'name' => $collection->name,
                'description' => $collection->description,
            ]);
        } else {
            return Response::notFound('not_found.phtml');
        }
    }

    /**
     * Update a collection
     *
     * @request_param string id
     *
     * @response 302 /login?redirect_to=/collection/:id/edit if not connected
     * @response 404 if the collection doesn’t exist or user hasn't access
     * @response 400 if csrf or name are invalid
     * @response 302 /collections/:id
     *
     * @param \Minz\Request $request
     *
     * @return \Minz\Response
     */
    public function update($request)
    {
        $user = utils\CurrentUser::get();
        $collection_id = $request->param('id');

        if (!$user) {
            return Response::redirect('login', [
                'redirect_to' => \Minz\Url::for('edit collection', ['id' => $collection_id]),
            ]);
        }

        $collection = $user->collection($collection_id);
        if (!$collection) {
            return Response::notFound('not_found.phtml');
        }

        $name = $request->param('name', '');
        $description = $request->param('description', '');

        $csrf = new \Minz\CSRF();
        if (!$csrf->validateToken($request->param('csrf'))) {
            return Response::badRequest('collections/edit.phtml', [
                'collection' => $collection,
                'name' => $name,
                'description' => $description,
                'error' => _('A security verification failed: you should retry to submit the form.'),
            ]);
        }

        $collection->name = trim($name);
        $collection->description = trim($description);
        $errors = $collection->validate();
        if ($errors) {
            return Response::badRequest('collections/edit.phtml', [
                'collection' => $collection,
                'name' => $name,
                'description' => $description,
                'errors' => $errors,
            ]);
        }

        $collection_dao = new models\dao\Collection();
        $collection_dao->save($collection);

        return Response::redirect('collection', ['id' => $collection->id]);
    }

    /**
     * Delete a collection
     *
     * @request_param string id
     * @request_param string from default is /collections/:id/edit
     *
     * @response 302 /login?redirect_to=:from if not connected
     * @response 302 :from if the collection doesn’t exist or user hasn't access
     * @response 302 :from if csrf is invalid
     * @response 302 /collections
     *
     * @param \Minz\Request $request
     *
     * @return \Minz\Response
     */
    public function delete($request)
    {
        $user = utils\CurrentUser::get();
        $collection_id = $request->param('id');
        $from = $request->param('from', \Minz\Url::for('edit collection', ['id' => $collection_id]));

        if (!$user) {
            return Response::redirect('login', [
                'redirect_to' => $from,
            ]);
        }

        $collection = $user->collection($collection_id);
        if (!$collection) {
            utils\Flash::set('error', _('This collection doesn’t exist.'));
            return Response::found($from);
        }

        $csrf = new \Minz\CSRF();
        if (!$csrf->validateToken($request->param('csrf'))) {
            utils\Flash::set('error', _('A security verification failed.'));
            return Response::found($from);
        }

        $collection_dao = new models\dao\Collection();
        $collection_dao->delete($collection->id);

        return Response::redirect('collections');
    }

    /**
     * Show the bookmarks page
     *
     * @response 302 /login?redirect_to=/bookmarks if not connected
     * @response 404 if the collection doesn’t exist or user hasn't access
     * @response 200
     *
     * @param \Minz\Request $request
     *
     * @return \Minz\Response
     */
    public function showBookmarks()
    {
        $user = utils\CurrentUser::get();
        if (!$user) {
            return Response::redirect('login', [
                'redirect_to' => \Minz\Url::for('bookmarks'),
            ]);
        }

        $bookmarks = $user->bookmarks();
        if (!$bookmarks) {
            \Minz\Log::error("User {$user->id} has no Bookmarks collection.");
            return Response::notFound('not_found.phtml', [
                'details' => _('It looks like you have no “Bookmarks” collection, you should contact the support.'),
            ]);
        }

        return Response::ok('collections/show_bookmarks.phtml', [
            'collection' => $bookmarks,
            'links' => $bookmarks->links(),
        ]);
    }
}
