<?php

namespace Islandora\PDX\ACDHService\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Islandora\Chullo\Uuid\IUuidGenerator;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use ML\JsonLd\JsonLd;

class ACDHController
{

    protected $uuidGenerator;

    public function __construct(Application $app, IUuidGenerator $uuidGenerator)
    {
        $this->uuidGenerator = $uuidGenerator;
    }
    
    /*
     *  Drupal Form Data - metakey processing
     */
    public function processFormValues($data)
    {
        
        if(empty($data) && !isset($data)) { return false; }
        
        $keys = array();
        
        foreach ($data as $key => $value)
        {            
            $keys[] = $key;            
        }
        
        return $keys;                
    }
    
    /*
     * This will get the contentType metafields from Fedora4, based on the ContentType
     */
    
    public function metaKeys($contentType = null)
    {
        /* Symphony replace the . to _ */
        $data = array();
        
        $data = array("asd", "http://aaaa_bbb/ccc", "age", "asd");
        
        return $data;
    }
    
    public function getSlugData($data)
    {
        if(empty($data) && !isset($data)) { return false; }
                        
        foreach ($data as $key => $value)
        {            
            if($key == "Slug") { $slugValue = $value; }
        }
        
        return $slugValue;       
        
    }

    public function create(Application $app, Request $request, $id)
    {                
        
        //get the FORM POST VALUES
        //ez csak egy ID
        $tx = $request->query->get('tx', "");
        
        //Check for format - sima rdf format ellenorzes
        $format = null;
        
        try 
        {
            //$format = \EasyRdf_Format::getFormat($contentType = $request->headers->get('Content-Type', 'text/turtle'));
            $format = \EasyRdf_Format::getFormat('text/turtle');        
        } 
        catch (\EasyRdf_Exception $e) 
        {            
            $app->abort(415, $e->getMessage());
        }
        
        /*
         * 
         * THE META DATA CGHECKING PART
         * 
         */
        $objFormVal = $request->request;
        // get the formkeys
        $formKeys = $this->processFormValues($objFormVal);
        //get the schema metakeys
        $metaKeys = $this->metaKeys();
        //if not empty the form or meta array then compare them
        if((!empty($formKeys) && isset($formKeys)) 
                && (!empty($metaKeys) && isset($metaKeys)) 
          )
        {              
            $metaDiff = array_diff($metaKeys, $formKeys);                                      
        }
        else
        {
            die("Missing data");
        }
        
        $slugValue = $this->getSlugData($objFormVal);
              
              
        //Now check if body can be parsed in that format
        if ($format) { //EasyRdf_Format
            
            //Fake IRI, default LDP one for current resource "<>" is not a valid IRI!
            $fakeUuid = $this->uuidGenerator->generateV5("derp");
            $fakeIri = "urn:uuid:$fakeUuid";
            $fakeParsedIri = new \EasyRdf_ParsedUri($fakeIri);
          
            $graph = new \EasyRdf_Graph();
            try {                
                $graph->parse($request->getContent(), $format->getName(), $fakeParsedIri);                                
                $jsonld = $graph->serialise('jsonld');                
            } catch (\EasyRdf_Exception $e) {
                $app->abort(415, $e->getMessage());
            }
          
            // Get the JSON-LD DocumentInstance.
            $json_doc = JsonLd::getDocument($jsonld);                        
            // Get the default graph.
            $graph = $json_doc->getGraph();
            // Try to get the node based on the fakeIri
            $node = $graph->getNode($fakeIri);            
            
            if (is_null($node)) {
                $nodes = $graph->getNodes();
                if (count($nodes) == 0) {
                    $node = $graph->createNode($fakeIri);
                }
            }
            
            if (($results = $node->getProperty('http://www.semanticdesktop.org/ontologies/2007/03/22/nfo/v1.2/uuid'))
              !== null
            ) {
                if (is_array($results)) {
                    $uuid = reset($results)->getValue();
                } else {
                    $uuid = $results->getValue();
                }
            } else {
                $uuid = $this->uuidGenerator->generateV4();
                $node->addPropertyValue('http://www.semanticdesktop.org/ontologies/2007/03/22/nfo/v1.2/uuid', $uuid);
            }
            
            
            $node->addPropertyValue('http://acdh.oeaw.ac.at/acdh:property', 'property11');

            
            if (($pcdm_coll = $graph->getNode('http://pcdm.org/models#ACDH')) === null) {
                $pcdm_coll = $graph->createNode('http://pcdm.org/models#ACDH');
            }
                        
            $node->addType($pcdm_coll);
            $node->removeProperty('http://www.islandora.ca/ontologies/2016/02/28/isl/v1.0/hasURN');
            $node->addPropertyValue(
                'http://www.islandora.ca/ontologies/2016/02/28/isl/v1.0/hasURN',
                'urn:uuid:'
                . $uuid
            );

            
            
            //Restore LDP <> IRI on serialised graph
            $compact = JsonLd::compact($json_doc->toJsonLd());
            $pcdm_collection_rdf = str_replace($fakeIri, '', JsonLd::toString($compact));
        }

        $urlRoute = $request->getUriForPath('/islandora/resource/');

        $subRequestPost = Request::create(
            $urlRoute.$id,
            'POST',
            array(),
            $request->cookies->all(),
            array(),
            $request->server->all(),
            $pcdm_collection_rdf
        );
        $subRequestPost->query->set('tx', $tx);
        $subRequestPost->headers->set('Content-Type', 'application/ld+json');
        // Reset the Content-Length incase the end user supplied some RDF.
        $subRequestPost->headers->set('Content-Length', strlen($pcdm_collection_rdf));
     
        /* addig http header infos */        
        $serverAsd = array('HTTP_SLUG' => $slugValue);
        $headerAsd = array('slug' => $slugValue);
        $subRequestPost->server->add($serverAsd);
        $subRequestPost->headers->add($headerAsd);
        
        $responsePost = $app->handle($subRequestPost, HttpKernelInterface::SUB_REQUEST, false);
        
        
        if (201 == $responsePost->getStatusCode()) {// OK, collection created
        
            //Lets take the location header in the response
            $collection_fedora_url = $responsePost->headers->get('location');
            $indirect_container_rdf = $app['twig']->render(
                'createIndirectContainerfromTS.json',
                array(
                  'resource' => $collection_fedora_url,
                )
            );
            
            $subRequestPut = Request::create(
                $urlRoute . $id,
                'PUT',
                array(),
                $request->cookies->all(),
                array(),
                $request->server->all(),
                $indirect_container_rdf
            );
            
            $subRequestPut->query->set('tx', $tx);
            $subRequestPut->headers->set('Slug', 'members');
            $subRequestPut->headers->set('Content-Type', 'application/ld+json');
            $subRequestPut->headers->set('Content-Length', strlen($indirect_container_rdf));
            $app['islandora.hostHeaderNormalize']($subRequestPut);
            
            //Here is the thing. We don't know if UUID of the collection we just created is already in the triple store.
            //So what to do?
            //We could just try to use our routes directly, but UUID check agains triplestore we could fail!
            //Let's invoke the controller method directly
            // $responsePut = $app->handle($subRequestPut, HttpKernelInterface::SUB_REQUEST, false);
            $responsePut = $app['islandora.resourcecontroller']->put(
                $app,
                $subRequestPut,
                $collection_fedora_url,
                "members"
            );
            if (201 == $responsePut->getStatusCode()) {// OK, indirect container created
                
                $islandora_collection_uri = $urlRoute.$uuid;
                //Include headers from the parent one, some of the last one. Basically rewrite everything
                $putHeaders = $responsePut->getHeaders();
                //Guzzle psr7 response objects are inmutable. So we have to make this an array and add directly
                $putHeaders['Link'] = array(
                    '<'.$collection_fedora_url.'>; rel="alternate"',
                    '<'.$urlRoute.$uuid.'/members>; rel="hub"',
                );
                $putHeaders['Location'] = array($islandora_collection_uri);
                $putHeaders['Content-Length'] = strlen($islandora_collection_uri);
                //Should i care about the etag?
                
                /*
                errro_log("isandora collection");
                error_log(print_r($islandora_collection_uri, true));
                 */
                error_log(print_R("headers: "));
                error_log(print_r($putHeaders, true));
                error_log(print_R("-----collection: "));
                error_log(print_r($islandora_collection_uri, true));
                
                
                return new Response($islandora_collection_uri, 201, $putHeaders);
            }

            return $responsePut;
            
        }
        
        //Abort if PCDM collection object could not be created
        $app->abort($responsePost->getStatusCode(), 'Failed creating PCDM Collection');
    }
    
    /**
     * Add a proxy object to the collection for the member object.
     *
     * @param Application $app
     *   The silex application.
     * @param Request     $request
     *   The Symfony request.
     * @param string      $id
     *   The UUID of the collection.
     * @param string      $member
     *   The UUID of the object to add to the collection.
     */
    public function addMember(Application $app, Request $request, $id, $member)
    {
        $tx = $request->query->get('tx', "");

        $urlRoute = $request->getUriForPath('/islandora/resource/');

        $members_uri = $app['islandora.idToUri']($member);
        if (is_a($members_uri, 'Symfony\Component\HttpFoundation\Response')) {
            return $members_uri;
        }

        $members_proxy_rdf = $app['twig']->render(
            'createOreProxy.json',
            array(
            'resource' => $members_uri,
            )
        );

        $fullUri = $app['islandora.idToUri']($id);
        if (is_a($fullUri, 'Symfony\Component\HttpFoundation\Response')) {
            return $fullUri;
        }

        $fullUri .=  '/members';

        $newRequest = Request::create(
            $urlRoute . $id . '/members-add/' . $member,
            'POST',
            array(),
            $request->cookies->all(),
            array(),
            $request->server->all(),
            $members_proxy_rdf
        );
        $newRequest->headers->set('Content-type', 'application/ld+json');
        $newRequest->headers->set('Content-Length', strlen($members_proxy_rdf));
        $response = $app['islandora.resourcecontroller']->post($app, $newRequest, $fullUri);
        if (201 == $response->getStatusCode()) {
            return new Response($response->getBody(), 201, $response->getHeaders());
        }
        //Abort if PCDM collection object could not be created
        return $response;
        //return new Response($response->getStatusCode(), 'Failed adding member to PCDM Collection');

    }
    
    
    /**
     * Remove the proxy object for the member from the collection.
     *
     * @param Application $app
     *   The silex application.
     * @param Request     $request
     *   The Symfony request.
     * @param string      $id
     *   The UUID of the collection.
     * @param string      $member
     *   The UUID of the object to remove from the collection.
     */
    public function removeMember(Application $app, Request $request, $id, $member)
    {
        $tx = $request->query->get('tx', "");
        $force = $request->query->get('force', 'FALSE');

        $urlRoute = $request->getUriForPath('/islandora/resource/');

        $collection_uri = $app['islandora.idToUri']($id);
        if (is_object($collection_uri)) {
            return $collection_uri;
        }

        $member_uri = $app['islandora.idToUri']($member);
        if (is_object($member_uri)) {
            return $member_uri;
        }

        $sparql_query = $app['twig']->render(
            'findOreProxy.sparql',
            array(
              'collection_member' =>  $collection_uri . '/members',
              'resource' => $member_uri,
            )
        );
        try {
            $sparql_result = $app['triplestore']->query($sparql_query);
            
        } catch (\Exception $e) {
            $app->abort(503, 'Chullo says "Triple Store Not available"');
        }
        if (count($sparql_result) > 0) {
            $existing_transaction = (!empty($tx));
            $newRequest = Request::create(
                $urlRoute . 'dummy/delete',
                'POST',
                array(),
                $request->cookies->all(),
                array(),
                $request->server->all()
            );
            if (!$existing_transaction) {
                // If we don't have a transaction create one here
                $response = $app['islandora.transactioncontroller']->create($app, $newRequest);
                $tx = $app['islandora.transactioncontroller']->getId($response);
            }
            $newRequest = Request::create(
                $urlRoute . 'dummy/delete?force=' . $force . '&tx=' . $tx,
                'DELETE',
                array(),
                $request->cookies->all(),
                array(),
                $request->server->all()
            );
            foreach ($sparql_result as $triple) {
                $response = $app['islandora.resourcecontroller']->delete($app, $newRequest, $triple->obj->getUri(), "");
                if (204 != $response->getStatusCode()) {
                    $app->abort($response->getStatusCode(), 'Error deleting object');
                }
            }
            if (!$existing_transaction) {
                // If this is not a passed in transaction, then we can commit it here.
                $response = $app['islandora.transactioncontroller']->commit($app, $newRequest, $tx);
                if (204 == $response->getStatusCode()) {
                    return $response;
                } else {
                    $app->abort($response->getStatusCode(), 'Failed removing member from PCDM Collection');
                }
            }
        }
        // Not sure what to return if the transaction hasn't been committed yet.
        return Response::create("", 204, array());
    }
}
