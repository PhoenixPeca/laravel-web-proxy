<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Url\Url;
use Illuminate\Support\Facades\Cookie;
use GuzzleHttp\Cookie\CookieJar;

class HttpClient extends Controller
{

    private $client, $local_base_url, $local_full_url, $request, $remote_url, $remote_cookies, $local_cookies, $cookie_jar;

    public function intialize($scheme, $site, $path = null) {
        $_getQ = (!empty($_GET) ? '?'.http_build_query($_GET) : '');

        $this->remote_url = $scheme.'://'.$site.'/'.$path.$_getQ;

        $this->local_base_url = '/'.$scheme.'/'.$site;
        $this->local_full_url = $this->local_base_url.'/'.$path.$_getQ;

        $this->client = new \GuzzleHttp\Client([
            'allow_redirects' => [
                'track_redirects' => true
            ],
            'http_errors' => false,
            'cookies' => true
        ]);

        $this->get_local_cookies_arr();
        $this->set_outbound_cookie_payload();

        $this->request = $this->client->request('GET', $this->remote_url, [
            'cookies' => $this->cookie_jar
        ]);

        $this->remote_cookies = $this->client->getConfig('cookies')->toArray();

        if ($this->redirect_to() !== false) {
            return redirect($this->redirect_to(), 302);
        }

        return $this->http_response();
    }

    private function redirect_to() {
        $redirect_hitory = $this->request->getHeader(
            \GuzzleHttp\RedirectMiddleware::HISTORY_HEADER
        );

        if (!empty($redirect_hitory)) {
            $remote_url_final = end($redirect_hitory);

            $url = Url::fromString($remote_url_final);
            $url_parts = [
                $url->getScheme(),
                $url->getHost(),
                ltrim($url->getPath(), '/')
            ];
            $_getQ = (!empty($url->getQuery()) ? '?'.http_build_query($url->getQuery()) : '');
            return implode('/', $url_parts).$_getQ;
        } else {
            return false;
        }
    }

    private function http_response() {
        $result = response($this->request->getBody(), $this->request->getStatusCode());
        $result->header('Content-Type', $this->request->getHeaderLine('Content-Type'));
        foreach ($this->remote_cookies as $cookie) {
            $result->cookie(base64_encode($cookie['Domain']).'|'.base64_encode($cookie['Name']), $cookie['Value'], $cookie['Max-Age'], $this->local_base_url.$cookie['Path']);
        }
        return $result;
    }

    private function get_local_cookies_arr() {
        $cookies = Cookie::get();
        $local_cookies = [];
        foreach ($cookies as $label => $value) {
            $label_set = explode('|', $label, 2);
            if (count($label_set) === 2) {
                $local_cookies[] = [
                    'Domain' => base64_decode($label_set[0]), // not being used but may help in future usecase
                    'Name' => base64_decode($label_set[1]),
                    'Value' => $value
                ];
            }
        }
        $this->local_cookies = $local_cookies;
    }

    private function set_outbound_cookie_payload() {
        $url = Url::fromString($this->remote_url);
        $cookies = [];
        foreach ($this->local_cookies as $cookie) {
            $cookies[$cookie['Name']] = $cookie['Name'];
        }
        $this->cookie_jar = CookieJar::fromArray($cookies, $url->getHost());
    }

}
