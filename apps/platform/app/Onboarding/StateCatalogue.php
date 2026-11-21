<?php

declare(strict_types=1);

namespace App\Onboarding;

use App\Enums\StateStatus;

/**
 * The 50 states and DC. Texas is live at launch; the others start on the
 * waitlist, and the super admin changes their status (web journey screen
 * 61). Only Texas has its regulator and hours filled in: the rest are
 * added from each regulator when a state is prepared, never guessed.
 */
final class StateCatalogue
{
    /**
     * @return list<array{code: string, name: string, status: StateStatus, regulator?: string, exam_vendor?: string, qualifying_hours?: int, ce_hours?: int}>
     */
    public static function all(): array
    {
        $names = [
            'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
            'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'DC' => 'District of Columbia', 'FL' => 'Florida',
            'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana',
            'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine',
            'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
            'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire',
            'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota',
            'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island',
            'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
            'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin',
            'WY' => 'Wyoming',
        ];

        $states = [];

        foreach ($names as $code => $name) {
            $states[] = $code === 'TX'
                ? [
                    'code' => 'TX',
                    'name' => 'Texas',
                    'status' => StateStatus::Live,
                    'regulator' => 'TREC',
                    'exam_vendor' => 'Pearson VUE',
                    // Sales agent qualifying education, and CE hours per two-year renewal.
                    'qualifying_hours' => 180,
                    'ce_hours' => 18,
                ]
                : ['code' => $code, 'name' => $name, 'status' => StateStatus::Waitlist];
        }

        return $states;
    }
}
