<?php

namespace App\Console\Commands;

use App\News;
use App\Source;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Tabuna\Similar\Similar;

class SimilarParseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rss:similar';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * @return Similar
     */
    protected function getSimilar(): Similar
    {
        $percent = config('smi.story.percent', 51);

        return new Similar(function (News $a, News $b) use ($percent) {

            // It makes no sense to compare identical strings
            if ($a->title === $b->title) {
                return true;
            }

            // more 5 hours
            if (abs($a->timestamp - $b->timestamp) > 18000) {
                return false;
            }

            // less 1 hour
            if (abs($a->timestamp - $b->timestamp) < 3600) {
                $percent -= 5;
            }

            /*
            if (soundex(\Str::ascii($a->title)) == soundex(\Str::ascii($b->title))) {
                return true;
            }
            */

            similar_text($a->title, $b->title, $copy);

            return $percent < $copy;
        });
    }

    /**
     * @param Collection $group
     *
     * @return Collection
     */
    protected function groupNews(Collection $group): Collection
    {
        $main = $group->first();

        if ($main->image === null) {
            $group->each(function (News $news) use (&$main) {
                if ($news->image !== null) {
                    $main->media = $news->media;
                }
            });
        }

        return collect([
            'main'  => $main,
            'items' => $group,
        ]);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $data = $this->getSimilar()
            ->findOut(Source::getLastNews()->all())
            ->filter(function (Collection $items) {
                return $items->count() >= config('smi.story.minCount');
            })
            ->filter(function (Collection $items) {
                return $items->pluck('domain')->unique()->count() > 1;
            })
            ->map(function (Collection $group) {
                return $this->groupNews($group);
            })
            ->toJson();


        Storage::put('/api/similar-news.json', $data);

        $time = round(microtime(true) - LARAVEL_START, 2);
        $this->info("Similar News Received: $time sec.");
    }
}
