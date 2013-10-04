<?php
namespace Peazie\BuildDeliveryBundle\Controller;

use Aws\Common\Aws;
use Aws\Ec2\Ec2Client;
use Aws\ElasticLoadBalancing\ElasticLoadBalancingClient;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

/**
 * @Route("/prod")
 * @Template()
 */
class ProductionController extends Controller
{

    private $aws;
    private $aws_services = array();

    /**
     * @Route("/", name="prod_index")
     * @Template()
     */
    public function indexAction(Request $r)
    {
        $elbs = static::getElbs();

        foreach( $elbs as $lb ) {
            $instances  = static::getElbInstances($lb);

            $row = array();
            $row['LoadBalancerName'] = $lb;
            $row['Instances'] = $instances['InstanceStates'];

            $data[] = $row;
        }

        //print '<pre>'; print_r($data); die;

        return array( 
            'data' => $data
        );
    }//index


    protected function getElbs() {
        $params = $this->container->getParameter('aws');
        return $params['available_elbs'];
    }//getElbs


    protected function getElbDetails($elb_names = null) 
    {
        if( empty($elb_names) || !is_array($elb_names) ) {
            $elb_config = $elb_names;
        } else {
            $params = $this->container->getParameter('aws');
            $elb_config = $params['available_elbs'];
        }

        $cache = $this->get('delivery.cache');
        if( !$data = $cache->get('elbs-list') ) {
            $elb  = static::getAwsService('elasticloadbalancing');
            $elbs = $elb->describeLoadBalancers(array('LoadBalancerNames' => $elb_config));
            $data = $elbs->toArray();
            $cache->set( 'elbs-list', $data, 30*60 );
        }

        return $data;
    }//getElbDetails


    protected function getElbInstances($elb_name = null) {
        if( empty($elb_name) ) {
            throw new \Exception("No configuration supplied. Please try again");
        }

        $cache = $this->get('delivery.cache');

        if( !$data = $cache->get('elb-instances-' . $elb_name ) ) {
            $params = $this->container->getParameter('aws');

            $elb  = static::getAwsService('elasticloadbalancing');
            $instances = $elb->describeInstanceHealth(array( 'LoadBalancerName' => (string) $elb_name ) );

            $data = $instances->toArray();
            $cache->set( 'elb-instances-' . $elb_name, $data, 30*30 );
        }

        return $data;
    }//getElbInstances


    protected function getAws()
    {
        if( !$this->aws ) {
            $params = $this->container->getParameter('aws');
            $this->aws = Aws::factory(array(
                'key'    => $params['access_key_id'],
                'secret' => $params['access_key_secret'],
                'region' => $params['default_region']
            ));
        }

        return $this->aws;
    }//getAws


    protected function getAwsService($service=null)
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

}//ProductionController
