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
class DefaultController extends Controller
{
    /**
     * @Route("/")
     * @Template()
     */
    public function indexAction(Request $r)
    {

        $cache = $this->get('cache');

        if( !$data = $cache->read('staging-build-list') ) {
            $params = $this->container->getParameter('jenkins');
            $client = new Client('http://dev.peazie.com:8080');

            $request = $client->get( '/job/PeazieCM-Staging-Deploy/api/json', array(), array( 
                    'auth' => array( 'auth' => $params['user'], $params['password'] )
                )
            );

            $response = $request->send();
            $data = $response->json();

            $cache->set( 'build-list', $data, 5*60 );
        }

        return array( 
            'data' => $data 
        );
    }//index
}
