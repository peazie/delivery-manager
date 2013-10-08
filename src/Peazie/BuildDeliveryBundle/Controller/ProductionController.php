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
        $group_instances = array();

        $groups = array();
        $elbs   = array();

        $aws    = $this->container->get('peazie.helper.aws');
        $groups = $aws->getAutoScaleGroups();
        $elbs   = $aws->getElbs();

        if( count($groups) > 0 && is_array($groups ) ) {
            foreach($groups as $glb) {
                foreach( $glb as $g) {
                    foreach($g['Instances'] as $i) {
                        $group_instances[] = $i['InstanceId'];
                    }
                }
            }
        }

        foreach( $elbs as $lb ) {
            $instances  = $aws->getElbInstances($lb);

            $row = array();
            $row['LoadBalancerName'] = $lb;

            foreach( $instances['InstanceStates'] as $i ) {
                if( !in_array( $i['InstanceId'], $group_instances ) ) {
                    $row['Instances'][] = $i;
                }
            }

            if( !empty($groups[$lb]) && is_array($groups[$lb]) ) {
                $row['AutoScaleGroups'] = $groups[ $lb ];
            }

            $data[] = $row;
        }

        return array( 
            'data' => $data
        );
    }//index


    /**
     * @Route("/instance/{instance_id}", name="prod_instance_detail", defaults={ "instance_id":null } )
     * @Template()
     */
    public function instanceAction($instance_id=null)
    {
        if( is_null($instance_id) ) {
            throw new \Exception("Instance ID is missing. WTF?");
        }

        $data = $this->container->get('peazie.helper.aws')->getInstanceDetail($instance_id);

        return array( 'data' => $data['Reservations'][0]['Instances'][0] );

    }//instanceDetailAction


}//ProductionController
