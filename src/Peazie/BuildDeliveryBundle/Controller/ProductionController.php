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
    /**
     * @Route("/", name="prod_index")
     * @Template()
     */
    public function indexAction(Request $r)
    {

	$data = static::getElbs();

	return array(
	    'data' => $data['LoadBalancerDescriptions']
	);
    }//index

    protected function getElbs() {
        $cache = $this->get('delivery.cache');

        if( !$data = $cache->get('elbs-list') ) {
            $params = $this->container->getParameter('aws');

            $aws = Aws::factory(array(
                'key'    => $params['access_key_id'],
                'secret' => $params['access_key_secret'],
                'region' => $params['default_region']
            ));

            $elb  = $aws->get('elasticloadbalancing');
            $elbs = $elb->describeLoadBalancers();
            $data = $elbs->toArray();
            $cache->set( 'elbs-list', $data, 30*60 );
        }

	return $data;
    }


    protected function getElbInstances($config = null) {
	if( !$config ) {
	    throw new \Exception("No configuration supplied. Please try again");
	}

	$cache = $this->get('delivery.cache');

	if( !$data = $cache->get('elbs-list') ) {
	    $params = $this->container->getParameter('aws');

	    $aws = Aws::factory(array(
		'key'    => $params['access_key_id'],
		'secret' => $params['access_key_secret'],
		'region' => $params['default_region']
	    ));

	    $elb  = $aws->get('elasticloadbalancing');
	    $elbs = $elb->describeLoadBalancers();
	    $data = $elbs->toArray();
	    $cache->set( 'elbs-list', $data, 30*60 );
	}

	return $data;
    }

}
