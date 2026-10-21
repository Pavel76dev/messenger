<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UserSettings
{
    /**
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        $defs = config('user_settings.definitions', []);
        $out = [];
        foreach ($defs as $key => $meta) {
            if (! is_string($key) || ! is_array($meta)) {
                continue;
            }
            $type = (string) ($meta['type'] ?? 'text');
            $default = $meta['default'] ?? ($type === 'checkbox' ? '0' : '');
            $out[] = [
                'key' => $key,
                'type' => $type,
                'group' => (string) ($meta['group'] ?? 'general'),
                'group_label' => (string) ($meta['group_label'] ?? 'Прочее'),
                'label' => (string) ($meta['label'] ?? $key),
                'description' => (string) ($meta['description'] ?? ''),
                'placeholder' => (string) ($meta['placeholder'] ?? ''),
                'default' => is_bool($default) ? ($default ? '1' : '0') : (string) $default,
                'max' => (int) ($meta['max'] ?? 2000),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    public function effectiveValues(User $user): array
    {
        $overrides = UserSetting::query()
            ->where('user_id', $user->id)
            ->pluck('value', 'key')
            ->all();

        $values = [];
        foreach ($this->definitions() as $def) {
            $key = $def['key'];
            if (array_key_exists($key, $overrides) && is_string($overrides[$key])) {
                // "0" для чекбокса — валидное значение
                $values[$key] = $overrides[$key];
            } else {
                $values[$key] = $def['default'];
            }
        }

        return $values;
    }

    /**
     * @return array<string, bool>
     */
    public function overriddenFlags(User $user): array
    {
        $keys = UserSetting::query()
            ->where('user_id', $user->id)
            ->pluck('key')
            ->all();

        $flags = [];
        foreach ($this->definitions() as $def) {
            $flags[$def['key']] = in_array($def['key'], $keys, true);
        }

        return $flags;
    }

    public function get(User $user, string $key): string
    {
        $defs = config('user_settings.definitions', []);
        if (! isset($defs[$key]) || ! is_array($defs[$key])) {
            return '';
        }

        $row = UserSetting::query()
            ->where('user_id', $user->id)
            ->where('key', $key)
            ->first();

        if ($row && is_string($row->value)) {
            return $row->value;
        }

        $default = $defs[$key]['default'] ?? '';

        return is_bool($default) ? ($default ? '1' : '0') : (string) $default;
    }

    public function getBool(User $user, string $key): bool
    {
        $value = strtolower(trim($this->get($user, $key)));

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param  array<string, string|bool|null>  $values
     *
     * @throws ValidationException
     */
    public function update(User $user, array $values): void
    {
        $defs = config('user_settings.definitions', []);
        $known = array_keys($defs);
        $unknown = array_diff(array_keys($values), $known);
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'values' => ['Неизвестный ключ настройки: '.implode(', ', $unknown)],
            ]);
        }

        foreach ($values as $key => $raw) {
            $meta = $defs[$key];
            $type = (string) ($meta['type'] ?? 'text');
            $max = (int) ($meta['max'] ?? 2000);

            if ($raw === null) {
                UserSetting::query()
                    ->where('user_id', $user->id)
                    ->where('key', $key)
                    ->delete();

                continue;
            }

            if (is_bool($raw)) {
                $raw = $raw ? '1' : '0';
            }

            if (! is_string($raw)) {
                throw ValidationException::withMessages([
                    "values.{$key}" => ['Значение должно быть строкой.'],
                ]);
            }

            if ($type === 'checkbox') {
                $normalized = strtolower(trim($raw));
                if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                    $raw = '1';
                } elseif (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                    $raw = '0';
                } else {
                    throw ValidationException::withMessages([
                        "values.{$key}" => ['Ожидается да/нет.'],
                    ]);
                }
            } else {
                // Пустой textarea = сброс к дефолту (часто тоже пустому)
                if (trim($raw) === '') {
                    UserSetting::query()
                        ->where('user_id', $user->id)
                        ->where('key', $key)
                        ->delete();

                    continue;
                }

                $validator = Validator::make(
                    ['value' => $raw],
                    ['value' => ['required', 'string', 'max:'.$max]]
                );
                if ($validator->fails()) {
                    throw ValidationException::withMessages([
                        "values.{$key}" => $validator->errors()->get('value'),
                    ]);
                }
            }

            UserSetting::query()->updateOrCreate(
                ['user_id' => $user->id, 'key' => $key],
                ['value' => $raw]
            );
        }
    }
}
