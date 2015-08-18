<?php
namespace Boot\RabbitMQ\Command;

use Boot\RabbitMQ\Consumer\ConsumerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AbstractWorkerCommand
 * @package Search\QueueConsumer
 */
abstract class AbstractConsumerCommand extends AbstractAMQPCommand
{
    const SUCCESS_EXIT_CODE = 0;
    const FAILED_EXIT_CODE = 1;

    /** @var  ConsumerInterface   */
    protected $consumer;

    /** @var int    Time limit in seconds    */
    protected $timeLimit = 0;

    /** @var  int   Interval in seconds */
    protected $interval = 5;

    /** @var int  */
    private $startTime = 0;

    /** @var int  */
    protected $resultState = self::SUCCESS_EXIT_CODE;

    /**
     * @param string            $name
     * @param ConsumerInterface $consumer
     * @param LoggerInterface   $logger
     */
    public function __construct($name, ConsumerInterface $consumer, LoggerInterface $logger = null)
    {
        $this->consumer = $consumer;
        parent::__construct($name);
    }

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    final protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Show verbose info
        if($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE && !defined('AMQP_DEBUG')) {
            define('AMQP_DEBUG', true);
        }

        // Connect to server
        if($this->connect()) {

            // Create channel
            $queueTemplate = $this->consumer->getQueueTemplate();
            $channel = $queueTemplate->createChannel();

            // Prepare process
            $this->prepareProcess();

            // Process messages (this loop never ends unless explicitly configured otherwise)
            // IMPORTANT: Note that for each incoming message the ConsumerInterface::handle() method is executed in the background.
            //while (!$this->expired()) {

            // Execute pre process
            $this->preProcess();

            // Iterate callbacks.
            while(count($channel->callbacks)) {

                // Wait for message
                $channel->wait();

            }

            // Execute post process
            $this->postProcess();

            //}

            // Close channel
            $channel->close();

            // Close connection
            $queueTemplate->getConnection()->close();

        }



        return $this->resultState;
    }


    /**
     * Connect to RabbitMQ
     */
    public function connect()
    {
        // Declare queue
        $queueTemplate = $this->consumer->getQueueTemplate();

        // Connect to server
        $connection = $queueTemplate->getConnection();
        if (!$connection->isConnected()) {
            $connection->reconnect();
        }

        // Declare queue
        $queueTemplate->getStrategy()->declareQueue($queueTemplate);
        $queueTemplate->getStrategy()->declareQualityOfService($queueTemplate);

        return true;
    }

    /**
     * @return bool
     */
    protected function expired()
    {
        // Check if there is a time limit
        if($this->timeLimit > 0) {
            if($this->startTime === 0) {
                // Set start time
                $this->startTime = time();
            } else if(time() < $this->startTime + $this->timeLimit) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prepare process.
     */
    protected function prepareProcess()
    {
        // Listen to incoming messages.
        $this->consumer->listen();
    }

    /**
     * Execute post process.
     */
    protected function postProcess()
    {
        // Sleep for a specified amount of seconds after all messages are processed.
        sleep($this->interval);
    }

    /**
     * Execute pre press.
     */
    protected function preProcess()
    {
        // By default nothing happens, this method is just here to be extended if needed.
    }

}