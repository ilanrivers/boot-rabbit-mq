<?php
namespace Boot\RabbitMQ\Producer;

use Boot\RabbitMQ\Template\QueueTemplate;

interface ProducerInterface
{
    /**
     * @param array $data
     * @return bool
     */
    public function publish(array $data);


    /**
     * @return QueueTemplate
     */
    public function getQueueTemplate();

    /**
     * Connect the producer to the queue.
     *
     * @return void
     */
    public function connect();
}
