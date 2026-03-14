<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tags\Schemas;

use App\Models\Tag;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class TagsSelect
{
    public static function make(?int $ownerId = null): Select
    {
        return Select::make('tags')
            ->multiple()
            ->relationship(
                name: 'tags',
                titleAttribute: 'name',
                modifyQueryUsing: fn (Builder $query): Builder => $ownerId !== null
                    ? $query->where('owner_id', $ownerId)->orderBy('sort_order')->orderBy('name')
                    : $query->orderBy('name'),
            )
            ->searchable()
            ->preload()
            ->native(false)
            ->createOptionForm([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                ColorPicker::make('color')
                    ->default('#f59e0b'),
            ])
            ->createOptionUsing(fn (array $data): int => self::createTag($data, $ownerId))
            ->columnSpanFull();
    }

    private static function createTag(array $data, ?int $ownerId = null): int
    {
        $resolvedOwnerId = (int) ($ownerId ?? Filament::auth()->id());

        $name = trim((string) ($data['name'] ?? ''));
        $slug = Str::slug($name);

        if ($slug === '') {
            $slug = 'tag';
        }

        $existingTag = Tag::query()
            ->where('owner_id', $resolvedOwnerId)
            ->where('slug', $slug)
            ->first();

        if ($existingTag !== null) {
            return (int) $existingTag->getKey();
        }

        $nextSortOrder = (int) (Tag::query()
            ->where('owner_id', $resolvedOwnerId)
            ->max('sort_order') ?? 0) + 10;

        $tag = Tag::query()->create([
            'owner_id' => $resolvedOwnerId,
            'name' => $name,
            'slug' => $slug,
            'color' => $data['color'] ?? null,
            'sort_order' => $nextSortOrder,
        ]);

        return (int) $tag->getKey();
    }
}
