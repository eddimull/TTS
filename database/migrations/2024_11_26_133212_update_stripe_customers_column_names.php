<?php

use App\Models\Contacts;
use App\Models\ProposalContacts;
use App\Models\StripeCustomers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stripe_customers', function (Blueprint $table) {
            $table->dropColumn('proposal_id');
            $table->dropColumn('status');
            $table->renameColumn('proposal_contact_id', 'contact_id');
            $table->renameColumn('stripe_account_id', 'stripe_customer_id');
        });

        // update contact_id to point to contacts instead of proposal_contacts
        // find each contact by linking proposal_contacts to contacts on email
        StripeCustomers::all()->each(function ($stripeCustomer) {
            $proposalContact = ProposalContacts::find($stripeCustomer->contact_id);
            if (!$proposalContact) {
                echo "No proposal contact found for id $stripeCustomer->contact_id \n";
                return;
            }
            $contact = Contacts::where('email', $proposalContact->email)->first();
            if (!$contact) {
                echo "No contact found for email $proposalContact->email \n";
                return;
            }
            $stripeCustomer->contact_id = $contact->id;
            $stripeCustomer->save();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stripe_customers', function (Blueprint $table) {
            $table->renameColumn('contact_id', 'proposal_contact_id');
            $table->integer('proposal_id');
            $table->renameColumn('stripe_customer_id', 'stripe_account_id');
        });
    }
};
