<?php

namespace App\Policies;

use App\Models\Bands;
use App\Models\Questionnaires;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class QuestionnairePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user, Bands $band): bool
    {
        return $user->canRead('questionnaires', $band->id);
    }

    public function view(User $user, Questionnaires $questionnaire): bool
    {
        return $user->canRead('questionnaires', $questionnaire->band_id);
    }

    public function create(User $user, Bands $band): bool
    {
        return $user->canWrite('questionnaires', $band->id);
    }

    public function update(User $user, Questionnaires $questionnaire): bool
    {
        return $user->canWrite('questionnaires', $questionnaire->band_id);
    }

    public function delete(User $user, Questionnaires $questionnaire): bool
    {
        return $user->canWrite('questionnaires', $questionnaire->band_id);
    }
}
