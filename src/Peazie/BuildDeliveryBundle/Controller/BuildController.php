<?php

namespace Peazie\BuildDeliveryBundle\Controller;

use Guzzle\Http\Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

/**
 * @Route("/build")
 * @Template()
 */
class BuildController extends Controller
{
    /**
     * @Route("/", name="build_index")
     * @Template()
     */
    public function indexAction(Request $r)
    {

        $cache  = $this->get('delivery.cache');
        $params = $this->container->getParameter('jenkins');
        $client = new Client($params['base_url']);

        if( !$data = $cache->get('build-list') ) {

            $request = $client->get( '/job/PeazieCM-Staging-Deploy/api/json', array(), array( 
                    'auth' => array( 'auth' => $params['user'], $params['password'] )
                )
            );

            $response = $request->send();
            $data = $response->json();

            $cache->set( 'build-list', $data, 60 );
        }

        $stable_build = $data['lastStableBuild'];
        $build_request = $client->get( "/job/PeazieCM-Staging-Deploy/{$stable_build['number']}/api/json", array(), array( 
                'auth' => array( 'auth' => $params['user'], $params['password'] )
            )
        );
        $build_response = $build_request->send();
        $build_data = $build_response->json();
        $data['lastStableBuildInfo'] = $build_data;

        return array( 
            'data' => $data 
        );
    }//index


    /**
     * @Route("/new", name="build_new")
     * @Template()
     */
    public function newAction()
    {
        $params = $this->container->getParameter('jenkins');
        $client = new Client($params['base_url']);

        $request = $client->post( "/job/PeazieCM-Staging-Deploy/build", array(), array( 
                'auth' => array( 'auth' => $params['user'], $params['password'] )
            )
        );

        $response = $request->send();
        $data     = $response->getBody();

        return array( 'data' => $data );
    }


    /**
     * @Route("/view/{build_number}", name="build_view")
     * @Template()
     */
    public function viewAction($build_number)
    {
        $cache = $this->get('delivery.cache');

        if( !$data = $cache->get('build-detail-' . $build_number ) ) {
            $params = $this->container->getParameter('jenkins');
            $client = new Client($params['base_url']);

            $request = $client->get( "/job/PeazieCM-Staging-Deploy/{$build_number}/api/json", array(), array( 
                    'auth' => array( 'auth' => $params['user'], $params['password'] )
                )
            );

            $response = $request->send();
            $data = $response->json();

            $cache->set( 'build-detail-' . $build_number , $data, 7*24*60*60 );
        }

        return array( 
            'build_number' => $build_number,
            'data' => $data 
        );

    }

}
