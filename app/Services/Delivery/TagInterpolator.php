<?php

namespace App\Services\Delivery;

class TagInterpolator
{
    public function interpolate(string $template, array $fields, array $pingResponse = []): string
    {
        $template = preg_replace('/\{\{([a-zA-Z0-9_.]+)\}\}/', '[$1]', $template);

        $result = preg_replace_callback('/\[([a-zA-Z0-9_.]+)\]/', function ($matches) use ($fields) {
            $key = $matches[1];
            $value = data_get($fields, $key, '');

            return is_scalar($value) ? (string) $value : json_encode($value);
        }, $template);

        return preg_replace_callback('/\{\$ping\.([^}]+)\}/', function ($matches) use ($pingResponse) {
            return (string) data_get($pingResponse, $matches[1], '');
        }, $result);
    }

    public function buildPayload(array $config, array $fields, array $pingResponse = []): array
    {
        if (! empty($config['custom_post_data'])) {
            $json = $this->interpolate($config['custom_post_data'], $fields, $pingResponse);
            $decoded = json_decode($json, true);

            return is_array($decoded) ? $decoded : ['payload' => $json];
        }

        $payload = [];
        foreach ($config['custom_data_mappings'] ?? [] as $mapping) {
            $output = $mapping['output_field'] ?? $mapping['input_field'];
            if (! empty($mapping['static_value'])) {
                $payload[$output] = $mapping['static_value'];
            } else {
                $payload[$output] = $fields[$mapping['input_field']] ?? null;
            }
        }

        return $payload;
    }
}
