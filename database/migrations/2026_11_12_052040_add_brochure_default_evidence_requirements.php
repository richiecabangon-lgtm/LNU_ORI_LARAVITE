<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->catalogue() as [$category, $name, $requirements]) {
            if ($requirements === []) {
                continue;
            }

            $slug = Str::slug($category.' '.$name, '_');
            if (strlen($slug) > 80) {
                $slug = substr($slug, 0, 69).'_'.hash('crc32b', $slug);
            }

            DB::transaction(function () use ($slug, $requirements): void {
                $type = DB::table('research_types')->where('slug', $slug)->lockForUpdate()->first();
                if (! $type || DB::table('research_evidence_requirements')->where('research_type_id', $type->id)->exists()) {
                    return;
                }

                $now = now();
                $rows = array_map(fn (string $requirement): array => [
                    'research_type_id' => $type->id,
                    'name' => $requirement,
                    'is_required' => false,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $requirements);

                DB::table('research_evidence_requirements')->insert($rows);
            });
        }
    }

    /**
     * Keep defaults that may have been edited or attached to submissions.
     */
    public function down(): void {}

    /**
     * @return list<array{0: string, 1: string, 2: list<string>}>
     */
    private function catalogue(): array
    {
        $rows = [];
        $add = function (string $category, string $name, array $requirements) use (&$rows): void {
            $rows[] = [$category, $name, $requirements];
        };

        $category = 'Completed and ongoing research';
        foreach (['Completed unpublished individual research', 'Completed unpublished collaborative research'] as $name) {
            $add($category, $name, ['Copy of completed research paper']);
        }
        foreach (['On-going research', 'On-going collaborative research'] as $name) {
            $add($category, $name, ['Work plan', 'Accomplishment report']);
        }

        foreach (['Regional/National research journal', 'Local refereed journal', 'LNU research journal', 'International refereed journal / ISI / Web of Science / CHEd accredited', 'Scopus-indexed journal'] as $venue) {
            $add('Published research', 'Published individual research - '.$venue, ['Copy of published article', 'Copy of journal cover']);
        }
        foreach (['Scopus', 'non-Scopus'] as $venue) {
            $add('Research citations', 'Citation in a '.$venue.' journal (per citation)', ['Copy of citing article']);
        }

        $levels = ['Unit/Department activity', 'College-wide', 'University-wide', 'Regional/National', 'International'];
        $roles = [
            ['Attendance', ['hour', 'hour', 'hour', 'day', 'day'], ['Certificate of attendance, participation, appreciation, or recognition', 'Special order for attendance or participation']],
            ['Organizer / Coordinator / Facilitator', array_fill(0, 5, 'project'), ['Special order', 'Certificate as organizer or coordinator', 'Certificate as facilitator']],
            ['Editor / Moderator / Judge', array_fill(0, 5, 'project'), ['Editorial role: special order', 'Editorial role: journal page listing editorial board or staff', 'Moderator: special order', 'Moderator: certificate', 'Judge: special order or invitation', 'Judge: certificate']],
            ['Committee member', array_fill(0, 5, 'project'), ['Special order', 'Certificate as committee member']],
            ['Resource person / Lecturer / Presenter / Speaker', array_fill(0, 5, 'presentation'), ['Invitation', 'Certificate as resource person, lecturer, speaker, or presenter', 'Special order for presenting']],
            ['Consultant', array_fill(0, 5, 'project'), ['Certification as consultant']],
        ];
        foreach ($roles as [$role, $units, $requirements]) {
            foreach ($levels as $index => $level) {
                $add('Research-related activities', $role.' - '.$level.' (per '.$units[$index].')', $requirements);
            }
        }

        foreach (['LNU', 'Local', 'International', 'ISI-indexed / Scopus'] as $venue) {
            foreach (['abstract', 'article'] as $unit) {
                $add('Refereeing in a peer-reviewed journal', 'Referee - '.$venue.' journal (per '.$unit.')', ['Communication with journal editor', 'Copy of refereed article', 'Certificate as reviewer', 'LNU special order as reviewer']);
            }
        }

        $secretary = ['Secretary: proceedings if possible, special order, or certification from the dean'];
        foreach (['Adviser', 'Statistician', 'Panel', 'Editor', 'Secretary', 'Instrument validator / evaluator'] as $role) {
            foreach (['undergraduate', 'graduate', 'dissertation'] as $level) {
                $add('Involvement in completed student theses', $role.' - completed '.$level.' thesis', $role === 'Secretary' ? $secretary : ['Approval sheet', 'Special order']);
            }
        }
        foreach (['Adviser', 'Statistician', 'Panel', 'Secretary'] as $role) {
            foreach (['undergraduate', 'graduate', 'dissertation'] as $level) {
                $add('Involvement in proposal hearing', $role.' - '.$level.' proposal hearing', $role === 'Secretary' ? $secretary : ['Special order']);
            }
        }

        $category = 'Funded research involvement';
        $add($category, 'Statistician of completed research (per research)', ['Certification as statistician']);
        $add($category, 'Panel of concept paper / proposal hearing (per paper)', ['Signed documentation of proceedings']);
        $add($category, 'Panel of completed research paper (per research)', ['Approval sheet']);
        foreach ([
            'Program/project leader (per hour)' => 'project leader',
            'Program/project member (per hour)' => 'project member',
            'Data gatherer / enumerator (per hour)' => 'data gatherer or enumerator',
            'Editor of funded research (per research)' => 'editor',
            'Data encoder / tabulator (per hour)' => 'data encoder or tabulator',
            'Compiler / binder / collator (per hour)' => 'compiler, binder, or collator',
        ] as $name => $role) {
            $add($category, $name, ['Special order as '.$role, 'Certification as '.$role]);
        }

        return $rows;
    }
};
