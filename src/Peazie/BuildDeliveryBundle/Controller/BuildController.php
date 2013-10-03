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

        $cache = $this->get('delivery.cache');

        if( !$data = $cache->get('build-list') ) {
            $params = $this->container->getParameter('jenkins');
            $client = new Client($params['base_url']);

            $request = $client->get( '/job/PeazieCM-Staging-Deploy/api/json', array(), array( 
                    'auth' => array( 'auth' => $params['user'], $params['password'] )
                )
            );

            $response = $request->send();
            $data = $response->json();

            $cache->set( 'build-list', $data, 30*60 );
        }

        return array( 
            'data' => $data 
        );
    }//index

    /**
     * @Route("/test/{build_number}", name="build_test")
     * @Template()
     */
    public function testAction($build_number)
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

            $cache->set( 'build-detail-' . $build_number , $data, 3*60*60 );
        }

        return array( 
            'build_number' => $build_number,
            'data' => $data 
        );

    }

    /**
     * @Route("/production/{build_number}", name="build_production")
     * @Template()
     */
    public function prodAction($build_number)
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

            $cache->set( 'build-detail-' . $build_number , $data, 3*60*60 );
        }

        return array( 
            'build_number' => $build_number,
            'data' => $data 
        );

    }

}
