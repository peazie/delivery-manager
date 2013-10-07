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
 * @Route("/deploy")
 * @Template()
 */
class DeployController extends Controller
{
    /**
     * @Route("/{strategy}/{elb}", name="prod_deploy", defaults={ "strategy":null, "elb":null })
     * @Template()
     */
    public function deployAction($strategy, $elb) 
    {
        if( is_null($strategy) || is_null($elb) ) {
            throw new \Exception('Required parameters cannot be null');
        }

        $data['LoadBalancerName' ] = $elb;
        $data['DeployStrategy']    = $strategy;

        return array( 'data' => $data );

    }//deploy
}
