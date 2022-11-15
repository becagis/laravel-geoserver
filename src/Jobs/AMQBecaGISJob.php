<?php
namespace BecaGIS\LaravelGeoserver\Jobs;

use BecaGIS\LaravelGeoserver\Http\Repositories\AMQRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AMQBecaGISJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $channel, $action, $data, $meta, $typeName;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($channel, $action, $data, $meta, $typeName)
    {
        $this->channel = $channel;
        $this->action = $action;
        $this->data = $data;
        $this->meta = $meta;
        $this->typeName = $typeName;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        AMQRepository::instance()->sendTargetAction(
            $this->channel, 
            $this->action, 
            $this->data, 
            $this->meta, 
            $this->typeName
        );
    }
}
