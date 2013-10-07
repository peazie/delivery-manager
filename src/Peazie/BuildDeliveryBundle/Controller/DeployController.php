<?php
namespace Peazie\BuildDeliveryBundle\Controller;

use Aws\Common\Aws;
use Aws\Ec2\Ec2Client;
use Aws\ElasticLoadBalancing\ElasticLoadBalancingClient;

use Guzzle\Http\Client;
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
     * @Route("/{strategy}/{elb}", name="deploy_form", defaults={ "strategy":null, "elb":null })
     * @Template()
     */
    public function deployAction($strategy, $elb) 
    {
        if( is_null($strategy) || is_null($elb) ) {
            throw new \Exception('Required parameters cannot be null');
        }

        $cache  = $this->get('delivery.cache');
        $params = $this->container->getParameter('jenkins');
        $client = new Client($params['base_url']);

        $builds = null;
        if( !$builds = $cache->get('build-list') ) {

            $request = $client->get( '/job/PeazieCM-Staging-Deploy/api/json', array(), array( 
                    'auth' => array( 'auth' => $params['user'], $params['password'] )
                )
            );

            $response = $request->send();
            $builds = $response->json();

            $cache->set( 'build-list', $builds, 3*60 );
        }

        if( !$lastBuildResults = $cache->get('build-list-' . $builds['lastStableBuild']['number'] ) ) {

            $lastBuildRequest = $client->get( $builds['lastStableBuild']['url'] . 'api/json', array(), array( 
                    'auth' => array( 'auth' => $params['user'], $params['password'] )
                )
            );

            $lastBuildResponse = $lastBuildRequest->send();
            $lastBuildResults  = $lastBuildResponse->json();

            $cache->set( 'build-list-' . $builds['lastStableBuild']['number'], $lastBuildResults, 3*60 );
        }

        $data['LoadBalancerName' ] = $elb;
        $data['DeployStrategy']    = $strategy;
        $data['JenkinsBuild']      = $lastBuildResults['number'];
        $data['HgRevision']        = $lastBuildResults['actions'][1]['mercurialNodeName'];

        return array( 'data' => $data );

    }//deploy


    /**
     * @Route("/", name="deploy_cf" )
     * @Template()
     */
    public function cfAction(Request $r)
    {
        if ($r->get('deploy_strategy') != 'build' ) {
            return $this->redirect( $this->generateUrl('prod_index') );
        }

        $deploy_elb    = (string) $r->get('deploy_elb');
        $jenkins_build = (int)    $r->get('jenkins_build');
        $hg_revision   = (string) $r->get('hg_rev');
        $instance_type = (string) $r->get('instance_type');
        $instance_num  = (int)    $r->get('instance_number');
        $access_key    = (string) $r->get('aws_key');
        $access_pass   = (string) $r->get('aws_pass');

        if( trim($deploy_elb) == "peazie-prod" ) {
            print "You are trying to deply to $deploy_elb"; die;
        }

        $stack_name    = $deploy_elb . '-web-' . $jenkins_build . '-' . substr( $hg_revision, 0, 6 ) . '-' . substr( md5(time() ), 0, 6 );

        $stack_config = array(
            'StackName'   => $stack_name,
            'TemplateURL' => 'https://s3-us-west-1.amazonaws.com/deploy.peazie.io/cf/peazie_web_autoscale.json',
            'DisableRollback'  => true,

            'Parameters'  => array(
                array(
                    'ParameterKey'   => 'HgBuild',
                    'ParameterValue' => $jenkins_build,
                ),
                array(
                    'ParameterKey'   => 'HgRev',
                    'ParameterValue' => $hg_revision,
                ),
                array(
                    'ParameterKey'   => 'NumberOfInstances',
                    'ParameterValue' => $instance_num,
                ),
                array(
                    'ParameterKey'   => 'InstanceType',
                    'ParameterValue' => $instance_type,
                ),
                array(
                    'ParameterKey'   => 'ELB',
                    'ParameterValue' => $deploy_elb,
                )
            ),
            'Tags' => array(
                array(
                    'Key'   => 'Name',
                    'Value' => 'autoscale prod ' . $jenkins_build . ' ' . substr( $hg_revision, 0, 6 ),
                ),
                array(
                    'Key'   => 'brand',
                    'Value' => 'peazie',
                ),
                array(
                    'Key'   => 'jenkins_build',
                    'Value' => $jenkins_build,
                ),
                array(
                    'Key'   => 'hg_revision',
                    'Value' => $hg_revision,
                ),
            ),
        );


        $cf = $this->container->get('peazie.helper.aws')->getService('cloudformation');
        $result = $cf->createStack($stack_config);
        $data = array_merge($result->toArray(), $stack_config); 

        return array( 'data' => $data );
    }//cfAction
}
