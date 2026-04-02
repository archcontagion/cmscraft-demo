<?php

namespace app\modules\api\services;

use craft\elements\GlobalSet;

/**
 * JSON shape for global sets using Craft’s native per-field serialization (not ContentSerializer).
 */
final class GlobalSetSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function serialize(GlobalSet $globalSet): array
    {
        return [
            'id' => $globalSet->id,
            'name' => $globalSet->name,
            'handle' => $globalSet->handle,
            'fields' => $globalSet->getSerializedFieldValues(),
        ];
    }
}
