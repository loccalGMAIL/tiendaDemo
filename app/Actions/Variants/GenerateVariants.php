<?php

namespace App\Actions\Variants;

use App\Enums\Module;
use App\Facades\Modules;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GenerateVariants
{
    /**
     * Genera las variantes combinando los valores elegidos de los atributos del módulo
     * (S, M, L × Negro, Blanco = 6 variantes). No duplica las que ya existen.
     *
     * @param  array<string, array<int, string>>  $selection  atributo => valores elegidos
     * @return int Cantidad de variantes creadas
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(Product $product, array $selection, User $actor): int
    {
        if (! $actor->isAdmin()) {
            throw new AuthorizationException('Solo un administrador puede generar variantes.');
        }

        if (! Modules::enabled(Module::Variants)) {
            throw ValidationException::withMessages(['selection' => 'El módulo Variantes está apagado.']);
        }

        $axes = $this->axes($selection);

        if ($axes === []) {
            throw ValidationException::withMessages(['selection' => 'Elegí al menos un valor de algún atributo.']);
        }

        return DB::transaction(function () use ($product, $axes): int {
            Product::query()->lockForUpdate()->findOrFail($product->getKey());

            // `attributes` también es una propiedad interna del modelo: se lee con getAttribute().
            $existing = $product->variants()->get()
                ->map(function ($variant): string {
                    $attributes = $variant->getAttribute('attributes');

                    return $this->signature(is_array($attributes) ? $attributes : []);
                })
                ->all();

            $position = (int) $product->variants()->max('position');
            $created = 0;

            foreach ($this->combinations($axes) as $combination) {
                if (in_array($this->signature($combination), $existing, true)) {
                    continue;
                }

                $product->variants()->create([
                    'name' => implode(' / ', $combination),
                    'attributes' => $combination,
                    'position' => ++$position,
                ]);

                $existing[] = $this->signature($combination);
                $created++;
            }

            return $created;
        });
    }

    /**
     * Valida la selección contra los atributos configurados y la ordena como en el módulo.
     *
     * @param  array<string, array<int, string>>  $selection
     * @return array<string, list<string>>
     */
    private function axes(array $selection): array
    {
        $configured = [];

        foreach ((array) Modules::setting(Module::Variants, 'attributes', []) as $attribute) {
            $configured[(string) ($attribute['name'] ?? '')] = array_map('strval', (array) ($attribute['values'] ?? []));
        }

        foreach ($selection as $attribute => $values) {
            if (! array_key_exists($attribute, $configured)) {
                throw ValidationException::withMessages(['selection' => "El atributo «{$attribute}» no está configurado en el módulo Variantes."]);
            }

            foreach ($values as $value) {
                if (! in_array($value, $configured[$attribute], true)) {
                    throw ValidationException::withMessages(['selection' => "«{$value}» no es un valor de {$attribute}."]);
                }
            }
        }

        $axes = [];

        foreach ($configured as $attribute => $allowed) {
            $chosen = array_values(array_filter($allowed, fn (string $value): bool => in_array($value, $selection[$attribute] ?? [], true)));

            if ($chosen !== []) {
                $axes[$attribute] = $chosen;
            }
        }

        return $axes;
    }

    /**
     * Producto cartesiano de los atributos.
     *
     * @param  array<string, list<string>>  $axes
     * @return list<array<string, string>>
     */
    private function combinations(array $axes): array
    {
        $result = [[]];

        foreach ($axes as $attribute => $values) {
            $next = [];

            foreach ($result as $partial) {
                foreach ($values as $value) {
                    $next[] = [...$partial, $attribute => $value];
                }
            }

            $result = $next;
        }

        return $result;
    }

    /**
     * Identifica una combinación sin importar el orden de las claves ni las mayúsculas.
     *
     * @param  array<string, string>  $attributes
     */
    private function signature(array $attributes): string
    {
        ksort($attributes);

        return mb_strtolower((string) json_encode($attributes, JSON_UNESCAPED_UNICODE));
    }
}
