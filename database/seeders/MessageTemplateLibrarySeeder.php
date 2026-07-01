<?php

namespace Database\Seeders;

use App\Models\LibraryMessageTemplate;
use Illuminate\Database\Seeder;

class MessageTemplateLibrarySeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'vertical_id' => 'solar',
                'channel' => 'email',
                'name' => 'Solar savings introduction',
                'sort_order' => 10,
                'subject' => '{{first_name}}, your solar estimate is ready',
                'body' => "Hi {{first_name}},\n\nThanks for your interest in solar. Based on your postcode ({{zipcode}}), we can arrange a no-obligation savings review.\n\nReply to this email or call {{phone1}} to book your survey.",
                'html_body' => '<p>Hi {{first_name}},</p><p>Thanks for your interest in solar. Based on your postcode (<strong>{{zipcode}}</strong>), we can arrange a no-obligation savings review.</p><p>Reply to this email or call {{phone1}} to book your survey.</p>',
                'preview_data' => [
                    'first_name' => 'Alex',
                    'zipcode' => 'SW1A 1AA',
                    'phone1' => '+447700900123',
                ],
            ],
            [
                'vertical_id' => 'insurance_auto',
                'channel' => 'email',
                'name' => 'Auto quote follow-up',
                'sort_order' => 20,
                'subject' => 'Your motor quote for {{first_name}}',
                'body' => "Hi {{first_name}},\n\nWe saved your auto insurance quote. Compare rates for your vehicle and lock in cover before your renewal date.\n\nEmail: {{email}}",
                'html_body' => '<p>Hi {{first_name}},</p><p>We saved your <strong>auto insurance</strong> quote. Compare rates for your vehicle and lock in cover before your renewal date.</p>',
                'preview_data' => [
                    'first_name' => 'Jordan',
                    'email' => 'jordan@example.com',
                ],
            ],
            [
                'vertical_id' => 'loans',
                'channel' => 'email',
                'name' => 'Loan application reminder',
                'sort_order' => 30,
                'subject' => 'Complete your loan application, {{first_name}}',
                'body' => "Hi {{first_name}},\n\nYou started a personal loan application with us. Finish in a few minutes to see your personalised rate.\n\nNeed help? Call {{phone1}}.",
                'html_body' => '<p>Hi {{first_name}},</p><p>You started a <strong>personal loan</strong> application. Finish in a few minutes to see your personalised rate.</p>',
                'preview_data' => [
                    'first_name' => 'Sam',
                    'phone1' => '+447700900456',
                ],
            ],
            [
                'vertical_id' => 'mortgage',
                'channel' => 'email',
                'name' => 'Mortgage rate update',
                'sort_order' => 40,
                'subject' => 'New mortgage rates for {{first_name}}',
                'body' => "Hi {{first_name}},\n\nRates have changed this week. Book a quick call to review remortgage and purchase options for your property in {{zipcode}}.",
                'html_body' => '<p>Hi {{first_name}},</p><p>Rates have changed this week. Book a quick call to review remortgage and purchase options for your property in {{zipcode}}.</p>',
                'preview_data' => [
                    'first_name' => 'Taylor',
                    'zipcode' => 'EH1 1AA',
                ],
            ],
            [
                'vertical_id' => 'insurance_home',
                'channel' => 'email',
                'name' => 'Home insurance check-in',
                'sort_order' => 50,
                'subject' => 'Protect your home, {{first_name}}',
                'body' => "Hi {{first_name}},\n\nYour home insurance renewal window is open. Compare buildings and contents cover tailored to {{zipcode}}.",
                'html_body' => '<p>Hi {{first_name}},</p><p>Your <strong>home insurance</strong> renewal window is open. Compare buildings and contents cover tailored to {{zipcode}}.</p>',
                'preview_data' => [
                    'first_name' => 'Morgan',
                    'zipcode' => 'M1 1AA',
                ],
            ],
            [
                'vertical_id' => 'insurance_life',
                'channel' => 'email',
                'name' => 'Life cover nurture',
                'sort_order' => 60,
                'subject' => '{{first_name}}, life cover options',
                'body' => "Hi {{first_name}},\n\nLife insurance protects what matters most. Answer a few questions to see indicative premiums for your age and postcode.",
                'html_body' => '<p>Hi {{first_name}},</p><p>Life insurance protects what matters most. Answer a few questions to see indicative premiums.</p>',
                'preview_data' => ['first_name' => 'Casey'],
            ],
            [
                'vertical_id' => 'loans',
                'channel' => 'sms',
                'name' => 'Loan status SMS',
                'sort_order' => 70,
                'subject' => null,
                'body' => 'Hi {{first_name}}, your loan application is waiting. Finish here: reply YES or call {{phone1}}.',
                'html_body' => null,
                'preview_data' => [
                    'first_name' => 'Alex',
                    'phone1' => '+447700900123',
                ],
            ],
            [
                'vertical_id' => 'solar',
                'channel' => 'sms',
                'name' => 'Solar survey SMS',
                'sort_order' => 80,
                'subject' => null,
                'body' => '{{first_name}}, book your free solar survey for {{zipcode}}. Reply YES to confirm.',
                'html_body' => null,
                'preview_data' => [
                    'first_name' => 'Alex',
                    'zipcode' => 'SW1A 1AA',
                ],
            ],
        ];

        foreach ($templates as $template) {
            LibraryMessageTemplate::query()->updateOrCreate(
                [
                    'vertical_id' => $template['vertical_id'],
                    'channel' => $template['channel'],
                    'name' => $template['name'],
                ],
                $template,
            );
        }
    }
}
