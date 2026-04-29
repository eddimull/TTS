<?php

namespace App\Services;

use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\QuestionnaireInstanceFields;
use App\Models\QuestionnaireInstances;
use App\Models\Questionnaires;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class QuestionnaireSnapshotService
{
    public function snapshot(
        Questionnaires $template,
        Bookings $booking,
        Contacts $recipient,
        User $sentByUser,
    ): QuestionnaireInstances {
        return DB::transaction(function () use ($template, $booking, $recipient, $sentByUser) {
            $instance = QuestionnaireInstances::create([
                'questionnaire_id' => $template->id,
                'booking_id' => $booking->id,
                'recipient_contact_id' => $recipient->id,
                'sent_by_user_id' => $sentByUser->id,
                'name' => $template->name,
                'description' => $template->description,
                'status' => QuestionnaireInstances::STATUS_SENT,
                'sent_at' => now(),
            ]);

            $idMap = $this->copyFields($template, $instance);
            $this->rewriteVisibilityRules($instance, $idMap);

            return $instance->fresh('fields');
        });
    }

    /** @return array<int,int> oldFieldId => newFieldId */
    private function copyFields(Questionnaires $template, QuestionnaireInstances $instance): array
    {
        $idMap = [];
        foreach ($template->fields()->orderBy('position')->get() as $sourceField) {
            $copy = QuestionnaireInstanceFields::create([
                'instance_id' => $instance->id,
                'source_field_id' => $sourceField->id,
                'type' => $sourceField->type,
                'label' => $sourceField->label,
                'help_text' => $sourceField->help_text,
                'required' => $sourceField->required,
                'position' => $sourceField->position,
                'settings' => $sourceField->settings,
                'visibility_rule' => $sourceField->visibility_rule, // rewritten in second pass
                'mapping_target' => $sourceField->mapping_target,
            ]);
            $idMap[$sourceField->id] = $copy->id;
        }
        return $idMap;
    }

    private function rewriteVisibilityRules(QuestionnaireInstances $instance, array $idMap): void
    {
        foreach ($instance->fields()->whereNotNull('visibility_rule')->get() as $field) {
            $rule = $field->visibility_rule;
            $oldDep = $rule['depends_on'] ?? null;
            if ($oldDep === null || !isset($idMap[$oldDep])) {
                continue;
            }
            $rule['depends_on'] = $idMap[$oldDep];
            $field->visibility_rule = $rule;
            $field->save();
        }
    }
}
