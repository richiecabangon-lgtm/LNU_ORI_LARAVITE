<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const LEGACY_SLUGS = [
        'journal_article', 'conference_paper', 'book_chapter', 'book', 'research_project',
        'creative_work', 'patent', 'extension', 'other',
    ];

    private const LEVELS = ['Unit/Department activity', 'College-wide', 'University-wide', 'Regional/National', 'International'];

    private const THESIS_LEVELS = ['Undergraduate', 'Graduate', 'Dissertation'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('research_types', function (Blueprint $table) {
            $table->string('category', 100)->nullable()->after('description');
        });

        $now = now();
        DB::table('research_types')->whereIn('slug', self::LEGACY_SLUGS)->update(['is_active' => false, 'deleted_at' => $now]);

        $sortOrder = (int) DB::table('research_types')->max('sort_order');
        foreach ($this->catalogue() as [$category, $name, $points, $description]) {
            $slug = Str::slug($category.' '.$name, '_');
            if (strlen($slug) > 80) {
                $slug = substr($slug, 0, 69).'_'.hash('crc32b', $slug);
            }
            DB::table('research_types')->insert([
                'slug' => $slug,
                'name' => $name,
                'category' => $category,
                'description' => $description,
                'is_active' => true,
                'sort_order' => ++$sortOrder,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('research_type_point_settings')->insert([
                'research_type' => $slug,
                'points_per_output' => $points,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $slugs = DB::table('research_types')->whereNotNull('category')->pluck('slug');
        DB::table('research_type_point_settings')->whereIn('research_type', $slugs)->delete();
        DB::table('research_types')->whereNotNull('category')->delete();
        DB::table('research_types')->whereIn('slug', self::LEGACY_SLUGS)->update(['is_active' => true, 'deleted_at' => null]);

        Schema::table('research_types', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    /**
     * @return list<array{0: string, 1: string, 2: float|int, 3: string|null}>
     */
    private function catalogue(): array
    {
        $rows = [];
        $add = function (string $category, string $name, float|int $points, ?string $description = null) use (&$rows): void {
            $rows[] = [$category, $name, $points, $description];
        };

        $category = 'Completed and ongoing research';
        $add($category, 'Completed unpublished individual research', 150, 'Not a requirement of a degree. Evidence: copy of the completed research paper.');
        $add($category, 'Completed unpublished collaborative research', 100, 'Points are prorated among members of the research team.');
        $add($category, 'On-going research', 100, 'Evidence: work plan and accomplishment report.');
        $add($category, 'On-going collaborative research', 100, 'Points are prorated among members of the research team.');

        $category = 'Published research';
        foreach ([
            'Regional/National research journal' => 150,
            'Local refereed journal' => 150,
            'LNU research journal' => 150,
            'International refereed journal / ISI / Web of Science / CHEd accredited' => 200,
            'Scopus-indexed journal' => 360,
        ] as $venue => $points) {
            $add($category, 'Published individual research - '.$venue, $points, 'Not a requirement for a degree. Collaborative research points are divided by the number of authors. Evidence: article and journal cover.');
        }

        $category = 'Research citations';
        $add($category, 'Citation in a Scopus journal (per citation)', 30, 'No points for self-citation. Divided by the number of authors for collaborative research.');
        $add($category, 'Citation in a non-Scopus journal (per citation)', 10, 'No points for self-citation. Divided by the number of authors for collaborative research.');

        $roles = [
            ['Attendance', [0.5, 1, 1.5, 10, 20], ['hour', 'hour', 'hour', 'day', 'day']],
            ['Organizer / Coordinator / Facilitator', [10, 20, 45, 90, 180], array_fill(0, 5, 'project')],
            ['Editor / Moderator / Judge', [6, 12, 15, 30, 60], array_fill(0, 5, 'project')],
            ['Committee member', [3, 6, 10, 20, 40], array_fill(0, 5, 'project')],
            ['Resource person / Lecturer / Presenter / Speaker', [10, 20, 45, 90, 180], array_fill(0, 5, 'presentation')],
            ['Consultant', [10, 20, 45, 90, 180], array_fill(0, 5, 'project')],
        ];
        foreach ($roles as [$role, $points, $units]) {
            foreach (self::LEVELS as $index => $level) {
                $add('Research-related activities', $role.' - '.$level.' (per '.$units[$index].')', $points[$index], 'For multiple involvement in one activity, the highest points are credited.');
            }
        }

        $category = 'Refereeing in a peer-reviewed journal';
        foreach (['LNU', 'Local', 'International', 'ISI-indexed / Scopus'] as $venue) {
            $add($category, 'Referee - '.$venue.' journal (per abstract)', 10);
            $add($category, 'Referee - '.$venue.' journal (per article)', 60);
        }

        $category = 'Involvement in completed student theses';
        foreach ([
            'Adviser' => [40, 50, 60],
            'Statistician' => [10, 15, 20],
            'Panel' => [10, 15, 20],
            'Editor' => [20, 25, 30],
            'Secretary' => [3, 4, 5],
            'Instrument validator / evaluator' => [5, 10, 15],
        ] as $role => $points) {
            foreach (self::THESIS_LEVELS as $index => $level) {
                $add($category, $role.' - completed '.strtolower($level).' thesis', $points[$index], 'Points are divided among the faculty involved when a student has more than one adviser, statistician or editor.');
            }
        }

        $category = 'Involvement in proposal hearing';
        foreach ([
            'Adviser' => [20, 25, 30],
            'Statistician' => [5, 7.5, 10],
            'Panel' => [5, 7.5, 10],
            'Secretary' => [1.5, 2, 2.5],
        ] as $role => $points) {
            foreach (self::THESIS_LEVELS as $index => $level) {
                $add($category, $role.' - '.strtolower($level).' proposal hearing', $points[$index]);
            }
        }

        $category = 'Funded research involvement';
        $add($category, 'Statistician of completed research (per research)', 20);
        $add($category, 'Panel of concept paper / proposal hearing (per paper)', 5);
        $add($category, 'Panel of completed research paper (per research)', 20);
        $add($category, 'Program/project leader (per hour)', 1, 'Ceiling: 40 points per semester.');
        $add($category, 'Program/project member (per hour)', 0.5, 'Ceiling: 30 points per semester.');
        $add($category, 'Data gatherer / enumerator (per hour)', 1.5, 'Ceiling: 40 points per semester.');
        $add($category, 'Editor of funded research (per research)', 20);
        $add($category, 'Data encoder / tabulator (per hour)', 1, 'Ceiling: 40 points per semester.');
        $add($category, 'Compiler / binder / collator (per hour)', 1, 'Ceiling: 40 points per semester.');

        $category = 'Intellectual property';
        $evidence = 'Highest points are credited if certificates were received in the same year.';
        $add($category, 'Copyright', 100, $evidence);
        $add($category, 'Trademark', 200, $evidence);
        $add($category, 'Industrial design', 200, $evidence);
        $add($category, 'Utility model (if accepted)', 200, $evidence);
        $add($category, 'Utility model (if published)', 250, $evidence);
        $add($category, 'Utility model (if granted)', 350, $evidence);
        $add($category, 'Patent (if accepted)', 200, $evidence);
        $add($category, 'Patent (if published)', 300, $evidence);
        $add($category, 'Patent (if granted)', 400, $evidence);

        return $rows;
    }
};
