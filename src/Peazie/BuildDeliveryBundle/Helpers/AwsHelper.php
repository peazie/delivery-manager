<?php
namespace Peazie\BuildDeliveryBundle\Helpers;

use Aws\Common\Aws;
use Aws\Ec2\Ec2Client;
use Aws\ElasticLoadBalancing\ElasticLoadBalancingClient;

class AwsHelper 
{

    private $aws;
    private $conf;
    private $services = array();

    public function __construct( $conf ) 
    {
        $this->conf = $conf;
    }

    public function getAws()
    {
        if( !$this->aws ) {
            $this->aws = Aws::factory(array(
                'key'    => $this->conf['access_key_id'],
                'secret' => $this->conf['access_key_secret'],
                'region' => $this->conf['default_region']
            ));
        }

        return $this->aws;
    }//getAws


    public function getService($service=null)
    {
        if( empty($service) ) {
            throw new \Exception('No services defined!');
        }

        if( empty($this->aws_services[$service]) ) {
            $aws = static::getAws();
            $this->aws_services[$service] = $aws->get($service);
        }

        return $this->aws_services[$service];
    }//getAwsService

}//AwsHelper
