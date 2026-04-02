<?php

namespace app\modules\api\controllers;

use app\modules\api\services\ContentSerializer;
use app\modules\api\services\GlobalSetSerializer;
use Craft;
use craft\elements\Entry;
use craft\elements\GlobalSet;
use craft\web\Controller;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * JSON API endpoints for entry content and CMS type metadata.
 */
class RestController extends Controller
{
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;

    /**
     * GET api/v1/entry?uri=page/foo — same field structure as the matched template’s `entry` variable.
     *
     * Query params:
     * - `uri` (required): element URI as stored in Craft (e.g. `page/about`, `blog/post-slug`, or empty for homepage).
     *   Use `uri=blog` for the blog index (there is no entry at that URI; it is handled like `templates/blog/index.twig`).
     * - `site` (optional): site handle when not using the current site.
     */
    public function actionEntry(): Response
    {
        $request = Craft::$app->getRequest();
        $uri = $request->getQueryParam('uri', '');
        if (!is_string($uri)) {
            throw new BadRequestHttpException('Parameter "uri" must be a string.');
        }

        $siteHandle = $request->getQueryParam('site');
        $siteId = null;
        if (is_string($siteHandle) && $siteHandle !== '') {
            $site = Craft::$app->getSites()->getSiteByHandle($siteHandle);
            if (!$site) {
                throw new BadRequestHttpException("Unknown site handle: {$siteHandle}");
            }
            $siteId = $site->id;
        }

        if ($uri === '') {
            $uri = '';
        }

        $normalizedPath = trim($uri, '/');
        if ($normalizedPath === 'blog') {
            return $this->respondBlogIndex($siteId);
        }

        $element = Craft::$app->getElements()->getElementByUri($uri, $siteId, true);

        if (!$element instanceof Entry) {
            throw new NotFoundHttpException('No entry found for that URI.');
        }

        $serializer = new ContentSerializer();

        return $this->asJson($serializer->serializeEntry($element));
    }

    /**
     * Blog index: `/blog` is rendered by `blog/index.twig`, not a section entry, so no element URI is `blog`.
     */
    private function respondBlogIndex(?int $siteId): Response
    {
        if ($siteId === null) {
            $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        }

        /** @var Entry[] $posts */
        $posts = Entry::find()
            ->section('blog')
            ->siteId($siteId)
            ->orderBy('postDate DESC')
            ->all();

        $serializer = new ContentSerializer();
        $rows = [];
        foreach ($posts as $post) {
            $rows[] = $serializer->serializeBlogListingPost($post);
        }

        return $this->asJson([
            'kind' => 'blogIndex',
            'title' => 'Blog',
            'uri' => 'blog',
            'posts' => $rows,
        ]);
    }

    /**
     * GET api/v1/globals — global sets and their custom fields (same data as Twig globals, e.g. header / footer).
     *
     * Query params:
     * - `handle` (optional): return a single set by handle (e.g. `header`). Omit to return all sets.
     * - `site` (optional): site handle when not using the current site.
     */
    public function actionGlobals(): Response
    {
        $request = Craft::$app->getRequest();
        $serializer = new GlobalSetSerializer();

        $siteHandle = $request->getQueryParam('site');
        $siteId = null;
        if (is_string($siteHandle) && $siteHandle !== '') {
            $site = Craft::$app->getSites()->getSiteByHandle($siteHandle);
            if (!$site) {
                throw new BadRequestHttpException("Unknown site handle: {$siteHandle}");
            }
            $siteId = $site->id;
        }

        if ($siteId === null) {
            $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        }

        $handleFilter = $request->getQueryParam('handle');
        if ($handleFilter !== null && !is_string($handleFilter)) {
            throw new BadRequestHttpException('Parameter "handle" must be a string.');
        }

        $query = GlobalSet::find()->siteId($siteId);

        if (is_string($handleFilter) && $handleFilter !== '') {
            $query->handle($handleFilter);
        }

        /** @var GlobalSet[] $globalSets */
        $globalSets = $query->all();

        if (is_string($handleFilter) && $handleFilter !== '' && count($globalSets) === 0) {
            throw new NotFoundHttpException("No global set found with handle “{$handleFilter}”.");
        }

        if (is_string($handleFilter) && $handleFilter !== '') {
            return $this->asJson($serializer->serialize($globalSets[0]));
        }

        $globalsByHandle = [];
        foreach ($globalSets as $globalSet) {
            $globalsByHandle[$globalSet->handle] = $serializer->serialize($globalSet);
        }


        return $this->asJson(['globals' => $globalsByHandle]);
    }

    /**
     * GET api/v1/types — sections, entry types, category groups, and asset volumes (handles and labels).
     */
    public function actionTypes(): Response
    {
        $entriesService = Craft::$app->getEntries();

        $sections = [];
        foreach ($entriesService->getAllSections() as $section) {
            $entryTypes = [];
            foreach ($entriesService->getEntryTypesBySectionId($section->id) as $entryType) {
                $entryTypes[] = [
                    'id' => $entryType->id,
                    'handle' => $entryType->handle,
                    'name' => $entryType->name,
                    'description' => $entryType->description,
                ];
            }

            $sections[] = [
                'id' => $section->id,
                'handle' => $section->handle,
                'name' => $section->name,
                'type' => $section->type,
                'entryTypes' => $entryTypes,
            ];
        }

        $allEntryTypes = [];
        foreach ($entriesService->getAllEntryTypes() as $entryType) {
            $allEntryTypes[] = [
                'id' => $entryType->id,
                'handle' => $entryType->handle,
                'name' => $entryType->name,
                'description' => $entryType->description,
            ];
        }

        $categoryGroups = [];
        foreach (Craft::$app->getCategories()->getAllGroups() as $group) {
            $categoryGroups[] = [
                'id' => $group->id,
                'handle' => $group->handle,
                'name' => $group->name,
            ];
        }

        $assetVolumes = [];
        foreach (Craft::$app->getVolumes()->getAllVolumes() as $volume) {
            $assetVolumes[] = [
                'id' => $volume->id,
                'handle' => $volume->handle,
                'name' => $volume->name,
            ];
        }

        return $this->asJson([
            'sections' => $sections,
            'entryTypes' => $allEntryTypes,
            'categoryGroups' => $categoryGroups,
            'assetVolumes' => $assetVolumes,
        ]);
    }
}
