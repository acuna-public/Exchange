<?php
  
  class WebSocket {
    
    /*------------------------------------------------------------------------*\
    Websocket client - https://github.com/paragi/PHP-websocket-client

    By Paragi 2013, Simon Riget MIT license.

    This is a demonstration of a websocket clinet.

    If you find flaws in it, please let me know at simon.riget (at) gmail

    Websockets use hybi10 frame encoding:

          0                   1                   2                   3
          0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
         +-+-+-+-+-------+-+-------------+-------------------------------+
         |F|R|R|R| opcode|M| Payload len |    Extended payload length    |
         |I|S|S|S|  (4)  |A|     (7)     |             (16/63)           |
         |N|V|V|V|       |S|             |   (if payload len==126/127)   |
         | |1|2|3|       |K|             |                               |
         +-+-+-+-+-------+-+-------------+ - - - - - - - - - - - - - - - +
         |     Extended payload length continued, if payload len == 127  |
         + - - - - - - - - - - - - - - - +-------------------------------+
         |                               |Masking-key, if MASK set to 1  |
         +-------------------------------+-------------------------------+
         | Masking-key (continued)       |          Payload Data         |
         +-------------------------------- - - - - - - - - - - - - - - - +
         :                     Payload Data continued ...                :
         + - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - +
         |                     Payload Data continued ...                |
         +---------------------------------------------------------------+

    See: https://tools.ietf.org/rfc/rfc6455.txt
    or:  http://tools.ietf.org/html/draft-ietf-hybi-thewebsocketprotocol-10#section-4.2
    
    \*----------------------------------------------------------------------- */
    
    /*======================================================================= *\
      Open websocket connection
      
      resource websocket_open (string $this->host [,int $this->port [,$additional_headers [,string &error_string [, int $this->timeout]]]]

      host
        A host URL. It can be a domain name like www.example.com or an IP address,
        with port number. Local host example: 127.0.0.1:8080

      port

      headers (optional)
        additional HTTP headers to attach to the request.
        For example to parse a session cookie: 'Cookie: SID=' . session_id()

      error_string (optional)
        A referenced variable to store error messages, i any

      timeout (optional)
        The maximum time in seconds, a read operation will wait for an answer from
        the server. Default value is 10 seconds.

      ssl (optional)  

      persistent (optional)

      path (optional)

      Context (optional)


      Open a websocket connection by initiating a HTTP GET, with an upgrade request
      to websocket.
      If the server accepts, it sends a 101 response header, containing
      'Sec-WebSocket-Accept'
    \*========================================================================*/
    
    public
      $headers = [],
      $requestHeaders = [],
      $timeout = 10,
      $ssl = false,
      $persistent = false,
      $path = '',
      $context = null,
      
      $responseHeaders = '',
      
      $final = true,
      $binary = true;
    
    protected $sp;
    
    function __construct ($host, $port = 80) {
      
      $this->host = $host;
      $this->port = $port;
      
    }
    
    function open () {
      
      // Connect to server
      
      $this->port = $this->port <= 0 ? ($this->ssl ? 443 : 80): $this->port;
      $address = ($this->ssl ? 'ssl://' : '').$this->host.':'.$this->port;
      
      $flags = STREAM_CLIENT_CONNECT | ($this->persistent ? STREAM_CLIENT_PERSISTENT : 0);
      
      if (!$this->context)
        $this->context = stream_context_create ();
      
      $this->sp = stream_socket_client ($address, $errno, $errstr, $this->timeout, $flags, $this->context);
      
      if (!$this->sp)
        throw new \WebSocketException ($errstr, $errno);
      
      // Set timeouts
      
      stream_set_timeout ($this->sp, $this->timeout);
      
      if (!$this->persistent or ftell ($this->sp) === 0) {
        
        // Request upgrade to websocket
        
        // Generate a key (to convince server that the update is not random)
        // The key is for the server to prove it i websocket aware. (We know it is)
        $key = base64_encode (openssl_random_pseudo_bytes (16));
        $url = parse_url ($this->host);
        
        $this->requestHeaders = [
          
          'GET /'.$this->path.' HTTP/1.1',
          'Host: '.$url['host'],
          'Accept: */*',
          'Connection: Upgrade',
          'Upgrade: WebSocket',
          'Pragma: no-cache',
          'Sec-WebSocket-Version: 13',
          'Sec-WebSocket-Key: '.$key,
          
        ];
        
        // Add extra headers
        
        foreach ($this->headers as $header)
          $this->requestHeaders[] = $header;
        
        // Add end of header marker
        
        $this->requestHeaders[] = '';
        $this->requestHeaders[] = '';
        
        $head = '';
        
        foreach ($this->requestHeaders as $header)
          $head .= $header.'\r\n<br/>';
        
        $rc = fwrite ($this->sp, implode ("\r\n", $this->requestHeaders));
        
        if (!$rc)
          throw new \WebSocketException ($errstr, $errno);
        
        // Read response into an assotiative array of headers. Fails if upgrade failes
        
        $this->responseHeaders = fread ($this->sp, 1024);
        
        // status code 101 indicates that the WebSocket handshake has completed
        
        if (
            stripos ($this->responseHeaders, ' 101 ') === false
          ||
            stripos ($this->responseHeaders, 'Sec-WebSocket-Accept: ') === false
        )
          throw new \WebSocketException ($this->responseHeaders);
        
        // The key we send is returned, concatenate with '258EAFA5-E914-47DA-95CA-
        // C5AB0DC85B11' and then base64-encoded. one can verify if one feels the need...
        
      }
      
    }
    
    /*========================================================================*\
      Write to websocket
      
      int websocket_write(resource $handle, string $data ,[boolean $this->final])

      Write a chunk of data through the websocket, using hybi10 frame encoding

      handle
        the resource handle returned by websocket_open, if successful

      data
        Data to transport to server

      final (optional)
        indicate if this block is the final data block of this request. Default true

      binary (optional)
        indicate if this block is sent in binary or text mode.  Default true/binary
    \*========================================================================*/
    
    function write ($data) {
      
      // Assemble header: FINal 0x80 | Mode (0x02 binary, 0x01 text)
      
      if ($this->binary)
        $header = chr (($this->final ? 0x80: 0) | 0x02); // 0x02 binary mode
      else
        $header = chr (($this->final ? 0x80 : 0) | 0x01); // 0x01 text mode
      
      // Mask 0x80 | payload length (0-125)
      
      if (strlen ($data) < 126)
        $header .= chr (0x80 | strlen ($data));
      elseif (strlen ($data) < 0xFFFF)
        $header .= chr (0x80 | 126).pack ('n', strlen ($data));
      else
        $header .= chr (0x80 | 127).pack ('N',0).pack ('N', strlen ($data));
      
      // Add mask
      
      $mask = pack ('N', rand (1,0x7FFFFFFF));
      $header .= $mask;
      
      // Mask application data
      
      for ($i = 0; $i < strlen ($data); $i++)
        $data[$i] = chr (ord ($data[$i]) ^ ord ($mask[$i % 4]));

      return fwrite ($this->sp, $header.$data);
      
    }
    
    /*========================================================================*\
      Read from websocket

      string websocket_read(resource $handle [,string &error_string])

      read a chunk of data from the server, using hybi10 frame encoding

      handle
        the resource handle returned by websocket_open, if successful

      error_string (optional)
        A referenced variable to store error messages, i any

      Read

      Note:
        - This implementation waits for the final chunk of data, before returning.
        - Reading data while handling/ignoring other kind of packages
    \*========================================================================*/
    
    function read ($callback) {
      
      $final = true;
      
      do {
        
        // Read header
        
        $header = fread ($this->sp, 2);
        
        if ($header) {
          
          $opcode = ord ($header[0]) & 0x0F;
          $final = ord ($header[0]) & 0x80;
          $masked = ord ($header[1]) & 0x80;
          $payload_len = ord ($header[1]) & 0x7F;
          
          // Get payload length extensions
          
          $ext_len = 0;
          
          if ($payload_len >= 0x7E) {
            
            $ext_len = 2;
            
            if ($payload_len == 0x7F) $ext_len = 8;
            
            $header = fread ($this->sp, $ext_len);
            
            if (!$header)
              throw new \WebSocketException ('Reading header extension from websocket failed');
            
            // Set extented paylod length
            
            $payload_len = 0;
            for ($i = 0; $i < $ext_len; $i++)
            $payload_len += ord ($header[$i]) << ($ext_len - $i - 1) * 8;
            
          }

          // Get Mask key
          
          if ($masked) {
            
            $mask = fread ($this->sp, 4);
            
            if (!$mask)
              throw new \WebSocketException ('Reading header mask from websocket failed');
            
          }
          
          // Get payload
          
          $frame_data = '';
          
          while ($payload_len > 0) {
            
            $frame = fread ($this->sp, $payload_len);
            
            if (!$frame)
              throw new \WebSocketException ('Reading from websocket failed');
            
            $payload_len -= strlen ($frame);
            $frame_data .= $frame;
            
          }

          // Handle ping requests (sort of) send pong and continue to read
          
          if ($opcode == 9) { // Assamble header: FINal 0x80 | Opcode 0x0A + Mask on 0x80 with zero payload
            
            fwrite ($this->sp, chr (0x8A).chr (0x80).pack ('N', rand (1,0x7FFFFFFF)));
            continue;
            
          } elseif ($opcode == 8) { // Close
            
            fclose ($this->sp);
            // 0 = continuation frame, 1 = text frame, 2 = binary frame
            
          } elseif ($opcode < 3) { // Unmask data
            
            if ($masked) {
              
              for ($i = 0; $i < strlen ($frame_data); $i++)
                $callback ($frame_data[$i] ^ $mask[$i % 4]);
              
            } else $callback ($frame_data);
            
          } else continue;
          
        }
        
      } while (!$final);
      
    }
    
  }