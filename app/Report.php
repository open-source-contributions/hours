<?php

declare(strict_types=1);

namespace App;

use App\Report\RendererFactory;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Output\OutputInterface;

class Report
{
    /**
     * @var Frame[]|Collection
     */
    private $frames;

    /**
     * @var CarbonInterface
     */
    private $from;

    /**
     * @var CarbonInterface
     */
    private $to;

    /**
     * @var string[]
     */
    private $projects;

    /**
     * @var string[]
     */
    private $tags;

    /**
     * @param Collection|Frame[] $frames
     * @param CarbonInterface    $from
     * @param CarbonInterface    $to
     * @param string[]           $projects
     * @param string[]           $tags
     */
    public function __construct(
        Collection $frames,
        CarbonInterface $from,
        CarbonInterface $to,
        array $projects = [],
        array $tags = []
    ) {
        $this->frames = $frames;
        $this->from = $from;
        $this->to = $to;
        $this->projects = $projects;
        $this->tags = $tags;
    }

    public static function build(): ReportBuilder
    {
        return new ReportBuilder();
    }

    /**
     * @return CarbonInterface
     */
    public function from(): CarbonInterface
    {
        return $this->from;
    }

    /**
     * @return CarbonInterface
     */
    public function to(): CarbonInterface
    {
        return $this->to;
    }

    public function headers(): Collection
    {
        return $this->data()->isNotEmpty() ? $this->data()->first()->keys() : collect($this->defaultHeaders());
    }

    public function data(): Collection
    {
        return $this->frames->map(function (Frame $frame) {
            return collect($this->defaultHeaders())
                ->combine([
                   $frame->project->name,
                   $frame->tags->implode('name', ', '),
                   $frame->started_at->presentDate(),
                   $frame->started_at->presentTime(),
                   $frame->stopped_at->presentTime(),
                   $frame->elapsed->presentInterval(),
               ]);
        })->unless($this->multipleProjects(), function (Collection $frames) {
            return $frames
               ->map(function (Collection $frame) {
                   return $frame->except('Project');
               });
        });
    }

    public function total(): CarbonInterval
    {
        return $this->frames
            ->pluck('elapsed')
            ->reduce(function (CarbonInterval $carry, CarbonInterval $item): CarbonInterval {
                return $item->add($carry);
            }, new CarbonInterval(null));
    }

    public function render(OutputInterface $output, string $format): void
    {
        app(RendererFactory::class)->make($format)->render($output, $this);
    }

    private function defaultHeaders(): array
    {
        return ['Project', 'Tags', 'Date', 'Start', 'End', 'Elapsed'];
    }

    private function multipleProjects(): bool
    {
        return count($this->projects) !== 1;
    }


    private function multipleTags(): bool
    {
        return count($this->tags) !== 1;
    }
}