<?php
namespace Peazie\BuildDeliveryBundle\Helpers;

use Aws\Common\Aws;
use Aws\Ec2\Ec2Client;
use Aws\ElasticLoadBalancing\ElasticLoadBalancingClient;

use Symfony\Component\DependencyInjection\Container;

class AwsHelper 
{

    private $aws;
    private $conf;
    private $services = array();
    private $container;

    public function __construct( Container $container, $conf ) 
    {
        $this->conf      = $conf;
        $this->container = $container;
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


    public function getElbs() {
        return $this->conf['available_elbs'];
    }//getElbs


    public function getElbDetails($elb_names = null) 
    {
        if( empty($elb_names) || !is_array($elb_names) ) {
            $elb_config = $elb_names;
        } else {
            $params = $this->container->getParameter('aws');
            $elb_config = $params['available_elbs'];
        }

        $cache = $this->container->get('delivery.cache');
        if( !$data = $cache->get('elbs-list') ) {
            $elb  = static::getService('elasticloadbalancing');
            $elbs = $elb->describeLoadBalancers(array('LoadBalancerNames' => $elb_config));
            $data = $elbs->toArray();
            $cache->set( 'elbs-list', $data, 60 );
        }

        return $data;
    }//getElbDetails


    public function getAutoScaleGroups() {

        $cache = $this->container->get('delivery.cache');

        if( !$data = $cache->get( 'autoscale-groups' ) ) {
            $as     = static::getService('autoscaling');
            $groups = $as->DescribeAutoScalingGroups()->toArray();

            foreach( $groups['AutoScalingGroups'] as $g ) {
                foreach( $g['LoadBalancerNames'] as $l ) {
                    $data[ $l ][] = $g;
                }
            }
            $cache->set( 'autoscale-groups', $data, 5*60 );
        }

        return $data;
    }//getAutoScaleGroups


    public function searchAutoScaleGroup($asg_name) 
    {

        $cache = $this->container->get('delivery.cache');

        if( !$data = $cache->get( 'autoscale-group-search-' . $asg_name ) ) {
            $as    = static::getService('autoscaling');
            $data  = $as->DescribeAutoScalingGroups( array( 'AutoScalingGroupNames' => array( $asg_name )) )->toArray();

            $cache->set( 'autoscale-group-search-' . $asg_name, $data, 3*60 );
        }

        return $data;
    }//getAutoScaleGroups


    public function getAutoScaleGroupTags($asg_name)
    {
        $cache = $this->container->get('delivery.cache');

        if( !$data = $cache->get( 'autoscale-group-tags-' . $asg_name ) ) {
            $group = static::searchAutoScaleGroup( $asg_name );
            $data  = $group['AutoScalingGroups'][0]['Tags'];

            $cache->set( 'autoscale-group-tags-' . $asg_name, $data, 3*60 );
        }

        return $data;
    }//getAutoScaleGroupTags


    public function setAutoScaleGroupScaling($asg_name, $capacity, $direction="up", $cooldown = false)
    {

        $asg = static::getService('autoscaling');

        $result = $asg->setDesiredCapacity(array(
            'AutoScalingGroupName' => $asg_name,
            'DesiredCapacity'      => $capacity,
            'HonorCooldown'        => $cooldown,
        ));

        return $result;
    }//getAutoScaleGroupTags


    public function getAutoScaleGroupCfStackName($asg_name)
    {
        $cache = $this->container->get('delivery.cache');

        if( !$data = $cache->get( 'autoscale-group-cf-stack-name-' . $asg_name ) ) {

            $tags  = static::getAutoScaleGroupTags($asg_name);

            foreach( $tags as $t ) {
                if($t['Key'] == 'aws:cloudformation:stack-name' ) {
                    $data = $t['Value'];
                }
            }

            $cache->set( 'autoscale-group-cf-stack-name-' . $asg_name, $data, 3*60 );
        }

        return $data;

    }//getAutoScaleGroupStackName

    public function getCloudFormation($stack_name)
    {
        $cache = $this->container->get('delivery.cache');

        if( !$data = $cache->get( 'autoscale-group-cf-stack-name-' . $asg_name ) ) {

            $tags  = static::getAutoScaleGroupTags($asg_name);

            foreach( $tags as $t ) {
                if($t['Key'] == 'aws:cloudformation:stack-name' ) {
                    $data = $t['Value'];
                }
            }

            $cache->set( 'autoscale-group-cf-stack-name-' . $asg_name, $data, 3*60 );
        }

    }//getCloudFormation

    public function getElbInstances($elb_name = null) {
        if( empty($elb_name) ) {
            throw new \Exception("No configuration supplied. Please try again");
        }

        $cache = $this->container->get('delivery.cache');

        if( !$data = $cache->get('elb-instances-' . $elb_name ) ) {
            $elb  = static::getService('elasticloadbalancing');
            $instances = $elb->describeInstanceHealth(array( 'LoadBalancerName' => (string) $elb_name ) );

            $data = $instances->toArray();
            $cache->set( 'elb-instances-' . $elb_name, $data, 30 );
        }

        return $data;
    }//getElbInstances


    public function getInstanceDetail($instance_id=null)
    {
        if( is_null($instance_id) ) {
            throw new \Exception("Instance ID is missing. WTF?");
        }

        if( is_string($instance_id) ) {
            $config = array( 'InstanceIds' => array($instance_id) );
        }

        if( is_array( $instance_id) ) {
            $config = array( 'InstanceIds' => $instance_id );
        }

        $cache = $this->container->get('delivery.cache');

        if( !$data = $cache->get('instance-detail-' . md5($instance_id) ) ) {

            $ec2       = static::getService('ec2');
            $instances = $ec2->describeInstances($config);
            $data      = $instances->toArray();

            $cache->set( 'instance-detail-' . md5($instance_id), $data, 30*30 );
        }

        return $data;
    }//getInstanceDetail

}//AwsHelper
