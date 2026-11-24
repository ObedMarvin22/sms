<?php

namespace App\Http\Resources\V1;

use App\Models\ReportCard;
use App\Models\TermResult;
use App\Support\Settings\SchoolSettings;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A published report card for the parent app: the term, the overall result and each subject's
 * grade. The position is included only when the school shows positions.
 *
 * @mixin ReportCard
 */
class ReportCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $showPositions = (bool) SchoolSettings::for(app(CurrentSchool::class)->require())->get('show_positions', false);

        return [
            'id' => $this->id,
            'term' => $this->term->name,
            'year' => $this->term->academicYear->name,
            'class' => $this->stream->full_name,
            'published_at' => $this->published_at?->toIso8601String(),
            'average' => $this->average === null ? null : (float) $this->average,
            'aggregate' => $this->aggregate,
            'position' => $showPositions ? $this->position : null,
            'class_size' => $this->class_size,
            'subjects' => TermResult::with('subject')
                ->where(['term_id' => $this->term_id, 'stream_id' => $this->stream_id, 'student_id' => $this->student_id])->get()
                ->sortBy(fn (TermResult $r) => $r->subject->name)->values()
                ->map(fn (TermResult $r) => [
                    'subject' => $r->subject->name,
                    'total' => $r->total === null ? null : (float) $r->total,
                    'grade' => $r->grade,
                    'meaning' => $r->grade_label,
                ])->all(),
            'teacher_remark' => $this->teacher_remark,
            'head_remark' => $this->head_remark,
        ];
    }
}
