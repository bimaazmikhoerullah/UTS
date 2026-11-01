<?php

namespace App\Jobs;

use App\Messaging\RabbitPublisher;
use App\Models\Outbox;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\Job;

class ProcessOutboxMessage implements ShouldQueue
{
    use Batchable;

    public function __construct(
        public int $outboxId,
    ) {
    }

    public function handle(Job $job): void
    {
        $outbox = Outbox::find($this->outboxId);

        if (!$outbox) {
            $job->delete();
            return;
        }

        if ($outbox->dikirim_pada !== null) {
            $job->delete();
            return;
        }

        try {
            $publisher = app(RabbitPublisher::class);
            $publisher->publish(
                routingKey: $outbox->routing_key,
                data: $outbox->payload,
                messageId: $outbox->message_id,
                type: $outbox->jenis,
            );

            $outbox->update(['dikirim_pada' => now()]);

            $job->delete();
        } catch (\Throwable $e) {
            if ($this->batching()) {
                $this->batch()->fail([
                    'outbox_id' => $this->outboxId,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($job->attempts() >= 3) {
                $job->fail([
                    'outbox_id' => $this->outboxId,
                    'error' => $e->getMessage(),
                ]);
            }

            $job->release(30);
        }
    }
}
