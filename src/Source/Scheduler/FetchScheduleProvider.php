<?php

declare(strict_types=1);

namespace App\Source\Scheduler;

use App\Source\Entity\Source;
use App\Source\Message\FetchSourceMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('fetch')]
final class FetchScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly int $defaultIntervalMinutes = 15,
    ) {
    }

    public function getSchedule(): Schedule
    {
        $schedule = new Schedule();

        /** @var list<Source> $sources */
        $sources = $this->entityManager
            ->getRepository(Source::class)
            ->findBy([
                'enabled' => true,
            ]);

        foreach ($sources as $source) {
            $id = $source->getId();
            if ($id === null) {
                continue;
            }

            $intervalMinutes = $source->getCategory()->getFetchIntervalMinutes()
                ?? $this->defaultIntervalMinutes;

            $schedule->add(
                RecurringMessage::every(sprintf('%d minutes', $intervalMinutes), new FetchSourceMessage($id)),
            );
        }

        return $schedule;
    }
}
