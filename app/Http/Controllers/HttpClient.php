<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Url\Url;
use Illuminate\Support\Facades\Cookie;
use GuzzleHttp\Cookie\CookieJar;

class HttpClient extends Controller
{

    private $client, $request, $remote_url, $remote_cookies, $local_cookie_jar;

    public function intialize($scheme, $site, $path = null) {
        $this->remote_url = $scheme.'://'.$site.'/'.$path.'?'.http_build_query($_GET);

        $this->client = new \GuzzleHttp\Client([
            'allow_redirects' => [
                'track_redirects' => true
            ],
            'http_errors' => false,
            'cookies' => true
        ]);

        $this->request = $this->client->request('GET', $this->remote_url);

        $this->remote_cookies = $this->client->getConfig('cookies')->toArray();

        if ($this->redirect_to() !== false) {
            return redirect($this->redirect_to(), 302);
        }

        $this->local_cookies();

        return $this->http_response();
    }

    private function redirect_to() {
        $redirect_hitory = $this->request->getHeader(
            \GuzzleHttp\RedirectMiddleware::HISTORY_HEADER
        );

        if (count($redirect_hitory) > 1) {
            $remote_url_final = end($redirect_hitory);

            $url = Url::fromString($remote_url_final);
            $url_parts = [
                $url->getScheme(),
                $url->getHost(),
                ltrim($url->getPath(), '/')
            ];
            return implode('/', $url_parts).'?'.$url->getQuery();
        } else {
            return false;
        }
    }

    private function http_response() {
        $result = response($this->request->getBody(), $this->request->getStatusCode());
        $result->header('Content-Type', $this->request->getHeaderLine('Content-Type'));
        foreach ($this->remote_cookies as $cookie) {
            $result->cookie(base64_encode($cookie['Domain']).'|'.base64_encode($cookie['Name']), $cookie['Value'], $cookie['Max-Age']);
        }
        return $result;
    }

    private function local_cookies() {
        $cookies = Cookie::get();
        $local_cookies = [];
        foreach ($cookies as $label => $value) {
            $label_set = explode('|', $label, 2);
            if (count($label_set) === 2) {
                $local_cookies[] = [
                    'Domain' => base64_decode($label_set[0]),
                    'Name' => base64_decode($label_set[1]),
                    'Value' => $value
                ];
            }
        }
        // $this->local_cookie_jar = CookieJar::fromArray($local_cookies);
        dd($this->remote_cookies);
    }
}
