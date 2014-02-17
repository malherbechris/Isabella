<?php

namespace App;

class Router extends Controller {

	function callee() {
		\Base::instance()->set('called',TRUE);
	}

	function get($f3) {
		$test=new \Test;
		$test->expect(
			is_null($f3->get('ERROR')),
			'No errors expected at this point'
		);
		$test->expect(
			$result=is_file($file=$f3->get('TEMP').'redir') &&
			$val=$f3->read($file),
			'Rerouted to this page'.($result?(': '.
				sprintf('%.1f',(microtime(TRUE)-$val)*1e3).'ms'):'')
		);
		if (is_file($file))
			@unlink($file);
		$f3->clear('ROUTES');
		$f3->route('GET|POST @hello:/',
			function($f3) {
				$f3->set('bar','foo');
			}
		);
		$f3->mock('GET @hello');
		$test->expect(
			$f3->get('bar')=='foo',
			'Named route'
		);
		$test->expect(
			$f3->get('ALIASES.hello')=='/',
			'Named route retrieved'
		);
		$f3->mock('GET /');
		$test->expect(
			$f3->get('bar')=='foo',
			'Routed to anonymous/lambda function'
		);
		$f3->clear('bar');
		$f3->mock('POST @hello');
		$test->expect(
			$f3->get('bar')=='foo',
			'Mixed request routing pattern'
		);
		$f3->clear('ROUTES');
		$f3->set('type','none');
		$f3->route('GET|POST / [ajax]',
			function($f3) {
				$f3->set('type','ajax');
			}
		);
		$f3->route('GET|POST / [sync]',
			function($f3) {
				$f3->set('type','sync');
			}
		);
		$f3->mock('GET /');
		$test->expect(
			$f3->get('type')=='sync',
			'Synchronous HTTP request'
		);
		$f3->mock('GET / [ajax]');
		$test->expect(
			$f3->get('type')=='ajax',
			'AJAX request'
		);
		$f3->clear('ROUTES');
		$f3->route('GET /',__NAMESPACE__.'\please');
			function please($f3) {
				$f3->set('send','money');
			}
		$f3->mock('GET /');
		$test->expect(
			$f3->get('send')=='money',
			'Routed to regular namespaced function'
		);
		$f3->clear('ROUTES');
		$f3->map('/dummy','NS\C');
		$ok=TRUE;
		$list='';
		foreach (explode('|',\Base::VERBS) as $verb) {
			$f3->mock($verb.' /dummy',array('a'=>'hello'));
			if ($f3->get('route')!=$verb ||
				preg_match('/GET|HEAD/',strtoupper($verb)) &&
				$f3->get('body') && !parse_url($f3->get('URI'),PHP_URL_QUERY))
				$ok=FALSE;
			else
				$list.=($list?', ':'').$verb;
		}
		$test->expect(
			$ok,
			'Methods supported'.($list?(': '.$list):'')
		);
		$f3->set('BODY','');
		$f3->mock('PUT /dummy');
		$test->expect(
			$f3->exists('BODY'),
			'Request body available'
		);
		$f3->mock('OPTIONS /dummy');
		$test->expect(
			preg_grep('/Allow: '.
				str_replace('|',',',\Base::VERBS.'/'),headers_list()),
			'HTTP OPTIONS request returns allowed methods'
		);
		$f3->clear('ERROR');
		$f3->clear('ROUTES');
		$f3->route('GET @grub:/food/@id',
			function($f3,$args) {
				$f3->set('id',$args['id']);
			}
		);
		$f3->set('PARAMS.id','fish');
		$f3->mock('GET @grub');
		$test->expect(
			$f3->get('PARAMS.id')=='fish' &&
			$f3->get('id')=='fish',
			'Parameter in route captured'
		);
		$f3->mock('GET @grub(@id=bread)');
		$test->expect(
			$f3->get('id')=='bread',
			'Different parameter in route'
		);
		$f3->route('GET @grub:/food/@id/@quantity',
			function($f3,$args) {
				$f3->set('id',$args['id']);
				$f3->set('quantity',$args['quantity']);
			}
		);
		$f3->set('PARAMS.id','pork');
		$f3->set('PARAMS.quantity',123);
		$test->expect(
			$f3->get('ALIASES.grub')=='/food/pork/123',
			'Default tokens in named route substituted'
		);
		$f3->mock('GET @grub(@id=beef,@quantity=789)');
		$test->expect(
			$f3->get('PARAMS.id')=='beef' &&
			$f3->get('PARAMS.quantity')==789 &&
			$f3->get('id')=='beef' && $f3->get('quantity')==789,
			'Multiple parameters'
		);
		$f3->mock('GET /food/macademia-nuts/253?a=1&b=3&c=5');
		$test->expect(
			$f3->get('PARAMS.id')=='macademia-nuts' &&
			is_numeric($qty=$f3->get('PARAMS.quantity')) && $qty==253 &&
			$_GET==array('a'=>1,'b'=>3,'c'=>5),
			'Query string mocked'
		);
		$f3->mock('GET /food/chicken/999?d=246&e=357',array('f'=>468));
		$test->expect(
			$_GET==array('d'=>246,'e'=>357,'f'=>468),
			'Query string and mock arguments merged'
		);
		$test->expect(
			$f3->get('id')=='chicken' && $f3->get('quantity')==999,
			'Route parameters captured along with query'
		);
		$f3->mock('GET @grub(@id=%C3%B6%C3%A4%C3%BC,@quantity=123)');
		$test->expect(
			$f3->get('id')=='öäü' && $f3->get('quantity')==123,
			'Unicode characters in URL (PCRE version: '.PCRE_VERSION.')'
		);
		$f3->clear('ROUTES');
		$mark=microtime(TRUE);
		$f3->route('GET /nothrottle',
			function($f3) {
				$f3->set('message','Perfect wealth becomes me');
			}
		);
		$f3->mock('GET /nothrottle');
		$test->expect(
			($elapsed=microtime(TRUE)-$mark) || TRUE,
			'Page rendering baseline: '.
				sprintf('%.1f',$elapsed*1e3).'ms'
		);
		$f3->clear('ROUTES');
		$mark=microtime(TRUE);
		$f3->route('GET /throttled',
			function($f3) {
				$f3->set('message','Perfect wealth becomes me');
			},
			0, /* don't cache */
			$throttle=16 /* 8Kbps */
		);
		$f3->mock('GET /throttled');
		$test->expect(
			$elapsed=microtime(TRUE)-$mark,
			'Same page throttled @'.$throttle.'Kbps '.
				'(~'.(1000/$throttle).'ms): '.
				sprintf('%.1f',$elapsed*1e3).'ms'
		);
		$f3->set('QUIET',TRUE);
		$f3->set('DNSBL','bl.spamcop.net');
		$f3->set('blocked',TRUE);
		$f3->route('GET /forum',
			function($f3) {
				$f3->set('blocked',FALSE);
			}
		);
		$mark=microtime(TRUE);
		$f3->mock('GET /forum');
		$test->expect(
			!$f3->get('blocked'),
			'DNSBL lookup: '.sprintf('%.1f',(microtime(TRUE)-$mark)*1e3).'ms'
		);
		$f3->set('QUIET',FALSE);
		$f3->clear('ROUTES');
		$f3->clear('called');
		$f3->call('App\Router->callee');
		$test->expect(
			$f3->get('called'),
			'Call method (NS\Class->method)'
		);
		$f3->clear('called');
		$obj=new Router;
		$f3->call(array($obj,'callee'));
		$test->expect(
			$f3->get('called'),
			'Call method (PHP array format)'
		);
		$f3->clear('called');
		$f3->call('App\callee');
		$test->expect(
			$f3->get('called'),
			'Call PHP function'
		);
		$f3->clear('called');
		$f3->call(function() {
			\Base::instance()->set('called',TRUE);
		});
		$test->expect(
			$f3->get('called'),
			'Call lambda function'
		);
		$test->expect(
			$f3->chain('App\a,App\b,App\c',1)==array(1,2,4),
			'Callback chain()'
		);
		$test->expect(
			$f3->relay('App\a,App\b,App\c',1)==8,
			'Callback relay()'
		);
		$f3->set('results',$test->results());
	}

}

function callee() {
	\Base::instance()->set('called',TRUE);
}

function a($x) {
	return $x;
}

function b($y) {
	return $y*2;
}

function c($z) {
	return $z*4;
}
