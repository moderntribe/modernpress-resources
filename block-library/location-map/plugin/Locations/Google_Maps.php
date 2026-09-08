<?php declare(strict_types=1);

namespace Tribe\Plugin\Locations;

use Tribe\Plugin\Settings\Tribe_Settings;

class Google_Maps {

	private const string SCRIPT_HANDLE = 'tribe-google-maps-loader';

	public function __construct(
		private Tribe_Settings $settings,
	) {
	}

	public function enqueue_frontend_loader(): void {
		if (
			! $this->settings->has_google_maps_api_key()
			|| is_admin()
			|| ! has_block( 'tribe/location-map' )
		) {
			return;
		}

		$this->enqueue_loader();
	}

	public function enqueue_editor_loader(): void {
		if ( ! $this->settings->has_google_maps_api_key() || ! is_admin() ) {
			return;
		}

		$this->enqueue_loader();
	}

	private function enqueue_loader(): void {
		$script = $this->get_loader_script();

		if ( $script === '' ) {
			return;
		}

		wp_register_script( self::SCRIPT_HANDLE, false, [], null, false );
		wp_enqueue_script( self::SCRIPT_HANDLE );
		wp_add_inline_script( self::SCRIPT_HANDLE, $script );
	}

	private function get_loader_script(): string {
		$api_key = wp_json_encode( $this->settings->get_google_maps_api_key() );

		if ( ! is_string( $api_key ) ) {
			return '';
		}

		return sprintf(
			'(g=>{var h,a,k,p="The Google Maps JavaScript API",c="google",l="importLibrary",q="__ib__",m=document,b=window;b=b[c]||(b[c]={});var d=b.maps||(b.maps={}),r=new Set,e=new URLSearchParams,u=()=>h||(h=new Promise(async(f,n)=>{await (a=m.createElement("script"));e.set("libraries",[...r]+"");for(k in g)e.set(k.replace(/[A-Z]/g,t=>"_"+t[0].toLowerCase()),g[k]);e.set("callback",c+".maps."+q);a.src=`https://maps.${c}apis.com/maps/api/js?`+e;d[q]=f;a.onerror=()=>h=n(Error(p+" could not load."));a.nonce=m.querySelector("script[nonce]")?.nonce||"";m.head.append(a)}));d[l]?console.warn(p+" only loads once. Ignoring:",g):d[l]=(f,...n)=>r.add(f)&&u().then(()=>d[l](f,...n))})({key:%s,v:"weekly"});',
			$api_key
		);
	}

}
