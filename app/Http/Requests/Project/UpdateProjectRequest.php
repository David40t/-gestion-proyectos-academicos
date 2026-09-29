<?php

namespace App\Http\Requests\Project;

class UpdateProjectRequest extends ProjectRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->projectRules();
    }
}
