<?php

namespace App\Services\Remedies;

use App\Models\Remedy;
use App\Models\RemedyAlias;
use InvalidArgumentException;

class RemedyResolver
{
    public function __construct(
        private readonly RemedyNormalizer $normalizer
    ) {}

    public function findByLegacyId(int|string|null $externalId, ?string $source = null): ?Remedy
    {
        if (! $externalId) {
            return null;
        }

        return Remedy::query()
            ->when($source, fn ($query) => $query->where('source', $source))
            ->where('external_id', (int) $externalId)
            ->first();
    }

    public function findByText(?string $value): ?Remedy
    {
        $normalized = $this->normalizer->normalize($value);

        if ($normalized === '') {
            return null;
        }

        $code = $this->normalizer->codeFromAbbreviationOrName($value, $value);

        $remedy = Remedy::query()
            ->where('normalized_name', $normalized)
            ->orWhere('normalized_abbreviation', $normalized)
            ->orWhere('code', $code)
            ->first();

        if ($remedy) {
            return $remedy;
        }

        $alias = RemedyAlias::query()
            ->with('remedy')
            ->where('normalized_alias', $normalized)
            ->first();

        return $alias?->remedy;
    }

    public function createOrUpdateFromLegacyRow(array $row, string $source = 'legacy_csv'): Remedy
    {
        $externalId = $this->value($row, ['id', 'ID', 'remedy_id']);
        $name = $this->value($row, ['name', 'Name', 'remedy_name']);
        $abbreviation = $this->value($row, ['abbreviation', 'Abbreviation', 'abbr', 'code']);

        if (! $name) {
            throw new InvalidArgumentException('Remedy name is required.');
        }

        $code = $this->normalizer->codeFromAbbreviationOrName($abbreviation, $name);

        if ($code === '') {
            throw new InvalidArgumentException('Remedy code could not be generated.');
        }

        $remedy = $externalId
            ? $this->findByLegacyId($externalId, $source)
            : null;

        $remedy ??= Remedy::query()->where('code', $code)->first();
        $remedy ??= new Remedy;

        $remedy->fill([
            'code' => $code,
            'name' => $name,
            'abbreviation' => $abbreviation,
            'normalized_name' => $this->normalizer->normalize($name),
            'normalized_abbreviation' => $this->normalizer->normalize($abbreviation),
            'source' => $source,
            'external_id' => $externalId ? (int) $externalId : null,
            'is_active' => true,
            'metadata' => [
                'legacy_row' => $row,
            ],
        ]);
        $remedy->save();

        $this->syncDefaultAliases($remedy, $source);

        return $remedy;
    }

    public function syncDefaultAliases(Remedy $remedy, ?string $source = null): void
    {
        $aliases = [
            ['alias' => $remedy->name, 'alias_type' => 'name'],
            ['alias' => $remedy->code, 'alias_type' => 'code'],
            ['alias' => $remedy->abbreviation, 'alias_type' => 'abbreviation'],
        ];

        foreach ($aliases as $item) {
            if (! $item['alias']) {
                continue;
            }

            RemedyAlias::updateOrCreate(
                [
                    'remedy_id' => $remedy->id,
                    'normalized_alias' => $this->normalizer->normalize($item['alias']),
                ],
                [
                    'alias' => $item['alias'],
                    'alias_type' => $item['alias_type'],
                    'source' => $source,
                ]
            );
        }
    }

    private function value(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }

        return null;
    }
}
