<?php

namespace App\Http\Controllers;

use Database\Seeders\DatasetSeeder;
use Database\Seeders\FixtureSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * "Reset demo data" in the admin's header. It puts every student and note
 * back to the original dataset, so the hosted demo can be tried freely and
 * then cleaned up in one click. Accounts and sign-ins stay, so the admin
 * stays signed in.
 */
class DemoController extends Controller
{
    /**
     * Empty the student tables and load the dataset again, in one
     * transaction, so a failed reset leaves the old data in place.
     *
     * The cache needs no clearing: what it holds for a student or a note
     * (an answer held for the advisor, a lock) is keyed by its UUID, and the
     * reloaded records get new ones.
     */
    public function reset(): Response
    {
        DB::transaction(function () {
            // On Postgres, Laravel's truncate also empties the tables that
            // point at the one named (CASCADE), so the order doesn't matter.
            DB::table('ai_briefs')->truncate();
            DB::table('contact_notes')->truncate();
            DB::table('students')->truncate();

            // The seeders set fields a form never may, such as sis_id.
            // db:seed lifts the mass-assignment guard for them; so does this.
            Model::unguarded(function () {
                app(DatasetSeeder::class)->run();
                app(FixtureSeeder::class)->run();
            });
        });

        return response()->noContent();
    }
}
