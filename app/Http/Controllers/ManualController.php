<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class ManualController extends Controller
{
    /**
     * The manual's sections in reading order, keyed by their anchor id (drives the table of contents).
     *
     * @var array<string, string>
     */
    public const SECTIONS = [
        'welcome' => 'Welcome',
        'roles' => 'Who can do what',
        'signing-in' => 'Signing in',
        'finding-your-way' => 'Finding your way',
        'shifts' => 'Shifts',
        'pos' => 'Making a sale (POS)',
        'jobs' => 'Repair jobs',
        'billing-jobs' => 'Billing a finished job',
        'bills' => 'Bills',
        'quotations' => 'Quotations',
        'warranty' => 'Warranty',
        'stock' => 'Stock & products',
        'contacts' => 'Customers & suppliers',
        'finance' => 'Finance',
        'salary' => 'Salary',
        'audit-log' => 'Audit log',
        'settings' => 'Settings & team',
        'normal-day' => 'A normal day',
        'faq' => 'Questions & problems',
    ];

    /**
     * The public staff user manual. It is kept out of search engines.
     */
    public function __invoke(): Response
    {
        return response()
            ->view('manual', ['sections' => self::SECTIONS])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
