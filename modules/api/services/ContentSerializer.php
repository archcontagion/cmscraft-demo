<?php

namespace app\modules\api\services;

use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\ckeditor\Field as CkeditorField;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use craft\elements\User;
use craft\fields\BaseRelationField;
use craft\fields\ContentBlock as ContentBlockField;
use craft\fields\data\LinkData;
use craft\fields\Link as LinkField;
use craft\fields\Matrix;
use DateTimeInterface;
use Illuminate\Support\Collection;

/**
 * Serializes Craft entries to arrays suitable for JSON — mirrors what Twig receives
 * for pages (field handles, nested matrix blocks, resolved assets and links).
 *
 * Full field serialization applies only to the entry requested via {@see serializeEntry()}
 * and to matrix/content blocks that belong to that page. Linked entries, relation targets,
 * and any other referenced entries are returned as shallow cards (no nested `fields`).
 *
 * Use {@see GlobalSetSerializer} for global sets.
 */
final class ContentSerializer
{
    /**
     * One row for the blog index listing (matches `templates/blog/index.twig`).
     *
     * @return array<string, mixed>
     */
    public function serializeBlogListingPost(Entry $post): array
    {
        $summary = $post->getFieldValue('summary');
        $summaryString = $summary !== null && $summary !== '' ? (string) $summary : null;

        $featureImage = $post->getFieldValue('featureImage');
        $asset = null;
        if ($featureImage instanceof ElementQueryInterface) {
            $asset = $featureImage->one();
        }

        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'uri' => $post->uri,
            'url' => $post->getUrl(),
            'postDate' => $post->postDate?->format(DateTimeInterface::ATOM),
            'summary' => $summaryString,
            'featureImage' => $asset instanceof Asset ? $this->serializeAssetCard($asset) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeEntry(Entry $entry): array
    {
        $section = $entry->getSection();
        $entryType = $entry->getType();

        return [
            'id' => $entry->id,
            'title' => $entry->title,
            'slug' => $entry->slug,
            'uri' => $entry->uri,
            'url' => $entry->getUrl(),
            'section' => $section ? [
                'handle' => $section->handle,
                'name' => $section->name,
                'type' => $section->type,
            ] : null,
            'entryType' => [
                'handle' => $entryType->handle,
                'name' => $entryType->name,
            ],
            'fields' => $this->serializeFields($entry),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeFields(ElementInterface $element): array
    {
        $out = [];

        $fieldLayout = $element->getFieldLayout();
        if ($fieldLayout === null) {
            return $out;
        }

        foreach ($fieldLayout->getCustomFields() as $field) {
            $handle = $field->handle;
            $value = $element->getFieldValue($handle);
            $out[$handle] = $this->serializeFieldValue($field, $value, $element);
        }

        return $out;
    }

    private function serializeFieldValue(FieldInterface $field, mixed $value, ElementInterface $element): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($field instanceof Matrix) {
            return $this->serializeMatrixField($value);
        }

        if ($field instanceof LinkField) {
            return $this->serializeLinkValue($value);
        }

        if ($field instanceof ContentBlockField) {
            return $this->serializeContentBlockValue($value);
        }

        if ($field instanceof CkeditorField) {
            return $value !== null && $value !== '' ? (string) $value : null;
        }

        if ($field instanceof BaseRelationField) {
            return $this->serializeRelationField($value);
        }

        return $this->serializeGenericValue($value);
    }

    private function serializeMatrixField(mixed $value): array
    {
        if ($value instanceof ElementQueryInterface) {
            $entries = $value->all();
        } elseif ($value instanceof Collection) {
            $entries = $value->all();
        } elseif (is_array($value)) {
            $entries = $value;
        } else {
            return [];
        }

        $blocks = [];
        foreach ($entries as $blockEntry) {
            if (!$blockEntry instanceof Entry) {
                continue;
            }

            $type = $blockEntry->getType();
            $blocks[] = [
                'id' => $blockEntry->id,
                'title' => $blockEntry->title,
                'slug' => $blockEntry->slug,
                'type' => $type->handle,
                'typeName' => $type->name,
                'fields' => $this->serializeFields($blockEntry),
            ];
        }

        return $blocks;
    }

    private function serializeLinkValue(mixed $value): ?array
    {
        if (!$value instanceof LinkData) {
            return null;
        }

        $linkElement = $value->getElement();

        return [
            'type' => $value->getType(),
            'url' => $value->getUrl(),
            'label' => $value->getLabel(),
            'target' => $value->target,
            'title' => $value->title,
            'element' => $linkElement instanceof ElementInterface
                ? $this->serializeRelatedElement($linkElement)
                : null,
        ];
    }

    private function serializeContentBlockValue(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof ElementInterface) {
            return null;
        }

        return [
            'id' => $value->id,
            'fields' => $this->serializeFields($value),
        ];
    }

    private function serializeRelationField(mixed $value): array
    {
        if ($value instanceof ElementQueryInterface) {
            $related = $value->all();
        } elseif ($value instanceof Collection) {
            $related = $value->all();
        } elseif (is_array($value)) {
            $related = $value;
        } else {
            return [];
        }

        $out = [];
        foreach ($related as $relatedElement) {
            if ($relatedElement instanceof ElementInterface) {
                $out[] = $this->serializeRelatedElement($relatedElement);
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRelatedElement(ElementInterface $element): array
    {
        if ($element instanceof Asset) {
            return $this->serializeAssetCard($element);
        }

        if ($element instanceof Entry) {
            $section = $element->getSection();
            $type = $element->getType();

            return [
                'kind' => 'entry',
                'id' => $element->id,
                'title' => $element->title,
                'slug' => $element->slug,
                'uri' => $element->uri,
                'url' => $element->getUrl(),
                'section' => $section ? [
                    'handle' => $section->handle,
                    'name' => $section->name,
                ] : null,
                'entryType' => [
                    'handle' => $type->handle,
                    'name' => $type->name,
                ],
            ];
        }

        if ($element instanceof Category) {
            return [
                'kind' => 'category',
                'id' => $element->id,
                'title' => $element->title,
                'slug' => $element->slug,
                'uri' => $element->uri,
                'url' => $element->getUrl(),
            ];
        }

        if ($element instanceof User) {
            return [
                'kind' => 'user',
                'id' => $element->id,
                'username' => $element->username,
                'fullName' => $element->fullName,
                'email' => $element->email,
            ];
        }

        return [
            'kind' => 'element',
            'class' => $element::class,
            'id' => $element->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAssetCard(Asset $asset): array
    {
        return [
            'kind' => 'asset',
            'id' => $asset->id,
            'title' => $asset->title,
            'filename' => $asset->filename,
            'url' => $asset->getUrl(),
            'mimeType' => $asset->getMimeType(),
            'width' => $asset->width,
            'height' => $asset->height,
        ];
    }

    private function serializeGenericValue(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if ($value instanceof ElementInterface) {
            return $this->serializeRelatedElement($value);
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return null;
    }
}
