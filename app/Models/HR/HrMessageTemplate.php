<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;

class HrMessageTemplate extends Model
{
    protected $table = 'hr_message_templates';

    protected $fillable = [
        'name',
        'category',
        'subject',
        'body',
        'variables',
        'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Render the template with a variable map.
     *
     * @param  array<string, mixed>  $data
     * @return array{subject: string, body: string}
     */
    public function render(array $data): array
    {
        $subject = $this->subject;
        $body = $this->body;

        foreach ($data as $key => $value) {
            $replacement = (string) $value;
            $subject = str_replace('{{' . $key . '}}', $replacement, $subject);
            $body = str_replace('{{' . $key . '}}', $replacement, $body);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}
