<?php
    
    /**
     * Simian API Library
     * (Simian.class.php)
     *
     * 2009
     * @author Wesley Bliss | http://wesleybliss.com/
     *
     */
    
    class Simian {
        
        //
        // command syntax example
        //      http://<...>/api.php?auth=<auth key>cmd=<command>&param1=value1 ... &paramN=valueN
        //
        
        // company namespace as per the company's private Simian URL
        protected $companyName;
        
        // auth key (can be retrieved from your Simian account installation)
        protected $authorizationKey;
        
        // primary Simian domain
        // current, as of this writing, ".gosimian.com"
        protected $primaryDomain;
        
        // API URLs
        protected $apiXMLURL;
        protected $apiRSSURL;
        
        // make sure we have the cache library enabled
        protected $cacheLibraryExists;
        
        
        function __construct( $companyName, $authorizationKey ) {
            
            // set credentials for Simian account access
            $this->companyName = $companyName;
            $this->authorizationKey = $authorizationKey;
            
            // default primary domain
            // current, as of this writing, ".gosimian.com"
            $this->primaryDomain = '.gosimian.com';
            
            // these values should not change unless the API changes
            $this->apiRSSURL = '';
            $this->apiXMLURL = 'api.php';
            
            // make sure we have the cache library
            $cacheLocalURI = 'cache.php';
            if ( strpos($_SERVER['REQUEST_URI'], '/inc/') <= -1 ) {
                $cacheLocalURI = ( 'inc/' . $cacheLocalURI );
            }
            if ( !file_exists($cacheLocalURI) ) {
                $this->cacheLibraryExists = false;
            }
            else {
                $this->cacheLibraryExists = true;
                require_once $cacheLocalURI;
            }
            
        } // __construct
        
        
        //
        // flattens an array to a string, in the format of
        // $key => $value, becomes $key=$value&$key2=$value2 (etc)
        //
        private function array2querystring( $array, $delimiter = '&', $encodeURI = false ) {
            $qs = '';
            $i = 1;
            foreach ( $array as $k => $v ) {
                $qs .= ( $k . '=' . ($encodeURI ? urlencode($v) : $v) . (($i >= count($array)) ? '' : $delimiter) );
                $i++;
            }
            return $qs;
        } // function array2querystring
        
        
        
        //
        // custom HTTP request with the option of being cached
        //
        private function httpRequest( $url, $enableCache = true ) {
            
            if ( !$this->cacheLibraryExists || !$enableCache ) {
                
                #$tmp__disabledCacheReason = ( !$this->cacheLibraryExists ? 'cache library not found' : 'manually disabled' );
                #echo "\n<!-- Simian : cache is disabled ($tmp__disabledCacheReason) -->\n";
                
                return trim( @file_get_contents($url) );
                
            }
            else {
                
                // see if we have a cached version of this request already
                if ( !cacheExists($url) ) {
                    // cache the request for reuse later
                    // the response doesn't really matter, since cacheRead() will
                    // fallback and load the real URL if it didn't cache properly
                    #echo "\n<!-- Simian : saving request to cache ($url) -->\n";
                    cacheWrite( $url );
                }
                
                // load cached version of URL, if exists || live URL
                return trim( cacheRead($url) );
                
            }
            
        } // function httpRequest()
        
        //
        // generic XML API query to the Simian library
        // $params = array() of parameters for the query
        // sample usage:
        //      http://<companyname>.gosimian.com/api.php?auth=<auth_key>&cmd=media&cat=8
        //
        public function queryXML( $params ) {
            
            /*
             * sample response
             *
             * <media>
             * 	    <spot>
             * 	        <id>981</id>
             *          <title>greenday iphone</title>
             *          <file>greenday-iphone.m4v</file>
             *          <fileType>video</fileType>
             *          <thumbnail>th_greenday-iphone.jpg</thumbnail>
             *          <category_id>8</category_id>
             *          <width>480</width>
             *          <height>270</height>
             *          <credits></credits>
             *          <description></description>
             *      </spot>
             *      <spot>
             *          <...>
             *      </spot>
             * </media>
             *
             */
            
            // full URL starting point of the query
            // example: http://<companyname>.gosimian.com/api.php?auth=<auth_key>
            $queryURL = (
                'http://' . $this->companyName .
                $this->primaryDomain . '/' . $this->apiXMLURL .
                '?auth=' . $this->authorizationKey /*.
                ( (count($params) > 0) ? ('&' . $this->array2querystring($params)) : '' )*/
            );
            
            //
            // add any parameters specified
            //
            
            // TODO: maybe return FALSE if $params is not empty AND not an array
            //       since that would be passing invalid data to this method
            
            // make sure $params is an array
            if ( is_array($params) ) {
                
                // make sure $params array is not empty
                if ( count($params) >= 1 ) {
                    
                    // append $params array, as a query string, to the $queryURL
                    $queryURL .= ( '&' . $this->array2querystring($params, '&', true) );
                    
                }
                
            }
            
            // TODO: trim() might need to be called after the URL request,
            //       since it might error this way... needs testing
            
            if ( !empty($_GET['debug']) ) {
                echo "\nQUERY URL: ", $queryURL, "\n\n";
            }
            
            //$resultXML = trim( @file_get_contents($queryURL) );
            
            $resultXML = $this->httpRequest( $queryURL, true );
            
            // return the query result XML
            return $resultXML;
            
        }
        
        
        //
        // query the media library
        // parameters:
        //      none: returns all records in media library
        //      id:   returns information no specific record
        //      cat:  returns media records in the specified category, designated by
        //            the category id (category ids can be obtained by using the "category" command)
        // returns:
        //      array of clip info, or explicit FALSE on failure
        //
        public function media( $id = '', $category = '' ) {
            
            /*
                EXAMPLE RESPONSE
                [id] => 376
                [title] => 10p
                [file] => http://[omitted cdn example].com/a_video_file.mov
                [fileType] => video
                [thumbnail] => http://[omitted cdn example]/assets/thumbs/a_video_file.jpg
                [file_thumbnail] => ...png
                [category_id] => 1
                [width] => 640
                [height] => 480
                [credits] => (string)
            */
            
            //
            // build a list of params, if used
            //
            
            // start off by specifying this type of query as a "Media" query
            $params = array(
                'cmd' => 'media'
            );
            
            // TODO: is there a simpler way to streamline this??
            
            if ( !empty($id) ) {
                $params['id'] = $id;
            }
            
            if ( !empty($category) ) {
                $params['cat'] = $category;
            }
            
            // make the query to the API
            $mediaXML = $this->queryXML( $params );
            
            // make sure the query succeeded (will return empty string on failure)
            if ( empty($mediaXML) || ($mediaXML == '') ) {
                
                // no data was received from the XML API request
                return false;
                
            }
            else {
                
                //
                // parse result XML
                //
                
                $xmlDoc = new DOMDocument();
                
                // TODO: better error returns here??
                
                try {
                    @$xmlDoc->loadXML( $mediaXML );
                }
                catch ( Exception $e ) {
                    return false;
                }
                
                // instantiate an XPath parser instance
                $xpath = new DOMXPath( $xmlDoc );
                
                // get all "spot"s returned
                $spots = $xpath->query( '//spot' );
                
                // convert all XML "spot"s to an array
                $media = array();
                
                foreach ( $spots as $spot ) {
                    
                    //
                    // get metadata associated with current spot
                    //
                    
                    //$id         = $xpath->query( 'id',       $spot )->item(0)->nodeValue;
                    //$title      = $xpath->query( 'title',    $spot )->item(0)->nodeValue;
                    //$file       = $xpath->query( 'file',     $spot )->item(0)->nodeValue;
                    //$fileType   = $xpath->query( 'fileType', $spot )->item(0)->nodeValue;
                    
                    // metadata to extract from this query
                    $meta = array(
                        'id'             => '',
                        'title'          => '',
                        'file'           => '',
                        'fileType'       => '',
                        'thumbnail'      => '',
                        'file_thumbnail' => '',
                        'category_id'    => '',
                        'width'          => '',
                        'height'         => '',
                        'credits'        => ''
                    );
                    
                    // cache a list of keys to look for, to save CPU cycles
                    // TODO: does PHP cache automatically?? is this necessary??
                    $meta_keys = array_keys( $meta );
                    
                    // extract each meta item
                    foreach ( $meta_keys as $k ) {
                        $meta[$k] = $xpath->query( $k, $spot )->item(0)->nodeValue;
                    }
                    
                    // free up resources
                    // TODO: is this even helpful??
                    unset( $meta_keys );
                    
                    // add all of the metadata we've parsed to the main result
                    array_push( $media, $meta );
                    
                }
                
                // return our $media array of spots with their metadata
                return $media;
                
            }
            
        } // function media
        
        
        //
        // gets a list of ALL categories in Simian
        // example:
        //      http://<companyname>.gosimian.com/api.php?auth=<auth_key>&cmd=category
        // returns: array of categories
        //
        public function categories() {
            
            $categoriesXML = $this->queryXML(
                array('cmd' => 'category')
            );
            
            // make sure the query succeeded (will return empty string on failure)
            if ( empty($categoriesXML) || ($categoriesXML == '') ) {
                
                // no data was received from the XML API request
                return false;
                
            }
            else {
                
                //
                // parse result XML
                //
                
                $xmlDoc = new DOMDocument();
                
                // TODO: better error returns here??
                
                try {
                    $xmlDoc->loadXML( $categoriesXML );
                }
                catch ( Exception $e ) {
                    return false;
                }
                
                // instantiate an XPath parser instance
                $xpath = new DOMXPath( $xmlDoc );
                
                // get all "spot"s returned
                $categoryNodes = $xpath->query( '//category' );
                
                // convert all XML "spot"s to an array
                $categories = array();
                
                foreach ( $categoryNodes as $category ) {
                    
                    //
                    // get metadata associated with current category
                    //
                    
                    // metadata to extract from this query
                    $meta = array(
                        'id'            => '',
                        'name'          => '',
                        'num_files'     => ''
                    );
                    
                    // cache a list of keys to look for, to save CPU cycles
                    // TODO: does PHP cache automatically?? is this necessary??
                    $meta_keys = array_keys( $meta );
                    
                    // extract each meta item
                    foreach ( $meta_keys as $k ) {
                        $meta[$k] = $xpath->query( $k, $category )->item(0)->nodeValue;
                    }
                    
                    // free up resources
                    // TODO: is this even helpful??
                    unset( $meta_keys );
                    
                    // add all of the metadata we've parsed to the main result
                    array_push( $categories, $meta );
                    
                }
                
                // return our $categories array of spots with their metadata
                return $categories;
                
            }
            
        } // function categories
        
        
        //
        // gets a list of ALL reels previously sent in reel history & stats in Simian
        // example:
        //      http://<companyname>.gosimian.com/api.php?auth=<auth_key>&cmd=reels
        //
        public function reels() {
            
            $reelsXML = $this->queryXML(
                array('cmd' => 'reels')
            );
            
            // make sure the query succeeded (will return empty string on failure)
            if ( empty($reelsXML) || ($reelsXML == '') ) {
                
                // no data was received from the XML API request
                return false;
                
            }
            else {
                
                //
                // parse result XML
                //
                
                $xmlDoc = new DOMDocument();
                
                // TODO: better error returns here??
                
                try {
                    $xmlDoc->loadXML( $reelsXML );
                }
                catch ( Exception $e ) {
                    return false;
                }
                
                // instantiate an XPath parser instance
                $xpath = new DOMXPath( $xmlDoc );
                
                // get all "spot"s returned
                $reelsNodes = $xpath->query( '//reel' );
                
                // convert all XML "spot"s to an array
                $reels = array();
                
                foreach ( $reelsNodes as $reel ) {
                    
                    //
                    // get metadata associated with current reel
                    //
                    
                    // metadata to extract from this query
                    $meta = array(
                        'id'            => '',
                        'name'          => '',
                        'date_sent'     => '',
                        'subject'       => '',
                        'views'         => '',
                        'sent_by'       => ''
                    );
                    
                    // cache a list of keys to look for, to save CPU cycles
                    // TODO: does PHP cache automatically?? is this necessary??
                    $meta_keys = array_keys( $meta );
                    
                    // extract each meta item
                    foreach ( $meta_keys as $k ) {
                        $meta[$k] = $xpath->query( $k, $reel )->item(0)->nodeValue;
                    }
                    
                    // free up resources
                    // TODO: is this even helpful??
                    unset( $meta_keys );
                    
                    // add all of the metadata we've parsed to the main result
                    array_push( $reels, $meta );
                    
                }
                
                // return our $reels array of spots with their metadata
                return $reels;
                
            }
            
        } // function reels
        
        
        //
        // returns all information associated with a reel in reel history & stats
        // example:
        //      http://<companyname>.gosimian.com/api.php?auth=<auth_key>&cmd=reel&id=3178
        //
        public function reel( $reelID ) {
            
            if ( !is_numeric($reelID) ) {
                return false;
            }
            
            $reelXML = $this->queryXML( array(
                'cmd' => 'reel',
                'id'  => $reelID
            ) );
            
            // make sure the query succeeded (will return empty string on failure)
            if ( empty($reelXML) || ($reelXML == '') ) {
                
                // no data was received from the XML API request
                return false;
                
            }
            else {
                
                //
                // parse result XML
                //
                
                $xmlDoc = new DOMDocument();
                
                // TODO: better error returns here??
                
                try {
                    $xmlDoc->loadXML( $reelXML );
                }
                catch ( Exception $e ) {
                    return false;
                }
                
                // instantiate an XPath parser instance
                $xpath = new DOMXPath( $xmlDoc );
                
                // get all "spot"s returned
                // TODO: make sure there's only one reel result (should be)
                $reelNode = $xpath->query( '//reel' );
                $reelNode = $reelNode->item(0);
                
                // convert all XML "spot"s to an array
                $reel = array();
                
                #foreach ( $reel as $reelMeta ) {
                    
                    //
                    // get metadata associated with current reel
                    //
                    
                    // metadata to extract from this query
                    $meta = array(
                        'id'            => '',
                        'name'          => '',
                        'date_sent'     => '',
                        'subject'       => '',
                        'views'         => '',
                        'sent_by'       => ''/*,
                        'spots'         => array() -- will be set later */
                    );
                    
                    // cache a list of keys to look for, to save CPU cycles
                    // TODO: does PHP cache automatically?? is this necessary??
                    $meta_keys = array_keys( $meta );
                    
                    // extract each meta item
                    foreach ( $meta_keys as $k ) {
                        $meta[$k] = @$xpath->query( $k, $reelNode )->item(0)->nodeValue;
                    }
                    
                    // free up resources
                    // TODO: is this even helpful??
                    unset( $meta_keys );
                    
                    // add all of the metadata we've parsed to the main result
                    array_push( $reel, $meta );
                    
                    //
                    // spots
                    //
                    
                    // get a list of spots for this reel
                    // TODO: make this xpath more efficient
                    $spots = $xpath->query( '//spot' );
                    
                    // for each spot, get associated metadata
                    foreach ( $spots as $spot ) {
                        
                        // metadata to extract from this current spot
                        $meta = array(
                            'id'             => '',
                            'title'          => '',
                            'file'           => '',
                            'fileType'       => '',
                            'thumbnail'      => '',
                            'file_thumbnail' => '',
                            'category_id'    => '',
                            'width'          => '',
                            'height'         => '',
                            'tags'           => array()/*,
                            'credits'        => array() -- will be set later */
                        );
                        
                        // cache a list of keys to look for, to save CPU cycles
                        // TODO: does PHP cache automatically?? is this necessary??
                        $meta_keys = array_keys( $meta );
                        
                        // extract each meta item
                        foreach ( $meta_keys as $k ) {
                            $meta[$k] = $xpath->query( $k, $spot )->item(0)->nodeValue;
                        }
                        
                        // tags, from Simian, come in CSV format, so split them into an array
                        $meta['tags'] = explode( ',', $meta['tags'] );
                        
                        $meta['credits'] = array();
                        $credits = $xpath->query( 'credits', $spot )->item(0);
                        $credits = $xpath->query( 'credit', $credits );
                        
                        foreach ( $credits as $credit ) {
                            // adds array item as (name => 'Director', value => 'Michael Bay')
                            //array_push(
                            //    $meta['credits'], array(
                            //        'name' => $xpath->query('name', $credit)->item(0)->nodeValue,
                            //        'value' => $xpath->query('value', $credit)->item(0)->nodeValue
                            //    )
                            //);
                            // adds an array item as (Director => 'Michael Bay')
                            // because it's all seperated by spaces coming from Simian
                            array_push(
                                $meta['credits'][
                                    strtolower($xpath->query('name', $credit)->item(0)->nodeValue)
                                ] = $xpath->query('value', $credit)->item(0)->nodeValue
                            );
                        }
                        
                        // free up resources
                        // TODO: is this even helpful??
                        unset( $meta_keys );
                        
                        // add all of the metadata we've parsed to the main result
                        $reel['spots'][] = $meta;
                        
                    }
                    
                    //
                    // TODO: same for credits, etc.
                    
                #}
                
                // return our $reel array of info about the selected reel with its metadata
                return $reel;
                
            }
            
        } // function reels
        
        
        
        //
        // gets a list of ALL directors in Simian
        // example:
        //      http://<account>.gosimian.com/api.php?auth=<auth_key>&cat=1&cmd=credits&c=Director
        // returns: array of directors
        //
        public function directors( $removeSimilar = false ) {
            
            $directorsXML = $this->queryXML(
                array(
                    'cmd'   =>    'credits',
                    'c'     =>    'Director'
                )
            );
            
            // make sure the query succeeded (will return empty string on failure)
            if ( empty($directorsXML) || ($directorsXML == '') ) {
                
                // no data was received from the XML API request
                return false;
                
            }
            else {
                
                //
                // parse result XML
                //
                
                $xmlDoc = new DOMDocument();
                
                // TODO: better error returns here??
                
                try {
                    $xmlDoc->loadXML( $directorsXML );
                }
                catch ( Exception $e ) {
                    return false;
                }
                
                // instantiate an XPath parser instance
                $xpath = new DOMXPath( $xmlDoc );
                
                //
                // get spots, etc. associated with director
                //
                
                $directors = array();
                
                /*
                    [example XML response]
                    
                    <credits>
                        <credit>
                            <name>Director</name>
                            <value>John Doe</value>
                        </credit>
                        ...
                    </credits>
                */
                
                $directorsNodes = $xpath->query( '//value' );
                
                foreach ( $directorsNodes as $director ) {
                    
                    $directorName = $director->nodeValue;
                    
                    if ( !empty($directorName) ) {
                        
                        array_push( $directors, $directorName );
                        
                    }
                    
                }
                
                return $directors;
                
            }
            
        } // function directors
        
        
        
        //
        // gets media associated with specified director
        // example:
        //      {pending}
        // returns: array of directors
        // c=<credit name>
        // cv=<credit value>
        //
        public function director( $directorName ) {
            
            $directorXML = $this->queryXML(
                array(
                    'cmd'   =>    'media',
                    'c'     =>    'Director',
                    'cv'    =>     $directorName
                )
            );
            
            // make sure the query succeeded (will return empty string on failure)
            if ( empty($directorXML) || ($directorXML == '') ) {
                
                // no data was received from the XML API request
                return false;
                
            }
            else {
                
                //
                // parse result XML
                //
                
                $xmlDoc = new DOMDocument();
                
                // TODO: better error returns here??
                
                try {
                    $xmlDoc->loadXML( $directorXML );
                }
                catch ( Exception $e ) {
                    return false;
                }
                
                // instantiate an XPath parser instance
                $xpath = new DOMXPath( $xmlDoc );
                
                //
                // get spots, etc. associated with director
                //
                
                $spots = array();
                
                $spotNodes = $xpath->query( '//spot' );
                
                // for each spot, get associated metadata
                foreach ( $spotNodes as $spot ) {
                    
                    // metadata to extract from this current spot
                    $meta = array(
                        'id'            => '',
                        'title'         => '',
                        'file'          => '',
                        'fileType'      => '',
                        'thumbnail'     => '',
                        'category_id'   => '',
                        'width'         => '',
                        'height'        => ''
                    );
                    
                    // cache a list of keys to look for, to save CPU cycles
                    // TODO: does PHP cache automatically?? is this necessary??
                    $meta_keys = array_keys( $meta );
                    
                    // extract each meta item
                    foreach ( $meta_keys as $k ) {
                        $meta[$k] = $xpath->query( $k, $spot )->item(0)->nodeValue;
                    }
                    
                    // free up resources
                    // TODO: is this even helpful??
                    unset( $meta_keys );
                    
                    // add all of the metadata we've parsed to the main result
                    $spots[] = $meta;
                    
                }
                
                // return our $directors array of spots with their metadata
                return $spots;
                
            }
            
        } // function directors
        
        
    } // Simian class
    
?>