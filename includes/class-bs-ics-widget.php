<?php
/**
 * Klassische WP_Widget-Variante für BS WP ICS Feed Reader (Sidebar/Widget-Bereiche).
 *
 * Ergänzt Shortcode und Gutenberg-Block um eine dritte Einbindungsmöglichkeit — für
 * Themes/Widget-Bereiche ohne Block-Widgets-Unterstützung sowie für Redakteure, die
 * lieber über die klassische Appearance-&-Widgets-Oberfläche arbeiten als per Shortcode.
 * Nutzt intern denselben BS_ICS_Renderer wie Shortcode und Block (keine Logik-Duplikation).
 *
 * @package BS_WP_ICS_Feed_Reader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasse BS_ICS_Widget
 */
class BS_ICS_Widget extends WP_Widget {

	/**
	 * Konstruktor.
	 */
	public function __construct() {
		parent::__construct(
			'bs_ics_widget',
			__( 'ICS Kalender-Feed', 'bs-wp-ics-feed-reader' ),
			[
				'description'                 => __( 'Zeigt Termine aus einem konfigurierten ICS-Kalender-Feed in einer Sidebar/einem Widget-Bereich an.', 'bs-wp-ics-feed-reader' ),
				'customize_selective_refresh' => true,
			]
		);
	}

	/**
	 * Rendert das Widget im Frontend.
	 *
	 * @param array $args     Widget-Bereichs-Argumente (before_widget, after_widget, ...).
	 * @param array $instance Gespeicherte Widget-Einstellungen.
	 */
	public function widget( $args, $instance ) {
		$feed_id = ! empty( $instance['feed_id'] ) ? absint( $instance['feed_id'] ) : 0;
		if ( ! $feed_id || BS_ICS_CPT::POST_TYPE !== get_post_type( $feed_id ) ) {
			return;
		}

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
		/** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );
		if ( ! empty( $title ) ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		// Baut dieselben Shortcode-Attribute, die BS_ICS_Renderer::render_shortcode()
		// ohnehin schon versteht — keine eigene Render-Logik, kein Duplikat.
		$shortcode_atts = [
			'id'          => $feed_id,
			'layout'      => ( 'grid' === ( $instance['layout'] ?? '' ) ) ? 'grid' : 'list',
			'limit'       => isset( $instance['limit'] ) ? absint( $instance['limit'] ) : 5,
			'only_future' => ! empty( $instance['only_future'] ) ? 'true' : 'false',
			'filter'      => ! empty( $instance['show_filter'] ) ? 'true' : 'false',
			'export'      => ! empty( $instance['show_export'] ) ? 'true' : 'false',
		];

		if ( class_exists( 'BS_ICS_Feed_Reader' ) ) {
			$renderer = BS_ICS_Feed_Reader::get_instance()->renderer;
			echo $renderer->render_shortcode( $shortcode_atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Rendert das Konfigurationsformular im Widget-Admin.
	 *
	 * @param array $instance Aktuelle Widget-Einstellungen.
	 */
	public function form( $instance ) {
		$title       = isset( $instance['title'] ) ? $instance['title'] : '';
		$feed_id     = isset( $instance['feed_id'] ) ? absint( $instance['feed_id'] ) : 0;
		$layout      = ( isset( $instance['layout'] ) && 'grid' === $instance['layout'] ) ? 'grid' : 'list';
		$limit       = isset( $instance['limit'] ) ? absint( $instance['limit'] ) : 5;
		$only_future = ! isset( $instance['only_future'] ) || ! empty( $instance['only_future'] );
		$show_filter = ! empty( $instance['show_filter'] );
		$show_export = ! empty( $instance['show_export'] );

		$feeds = get_posts(
			[
				'post_type'      => BS_ICS_CPT::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Titel:', 'bs-wp-ics-feed-reader' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'feed_id' ) ); ?>"><?php esc_html_e( 'Kalender-Feed:', 'bs-wp-ics-feed-reader' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'feed_id' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'feed_id' ) ); ?>">
				<option value="0"><?php esc_html_e( '– Kalender-Feed auswählen –', 'bs-wp-ics-feed-reader' ); ?></option>
				<?php foreach ( $feeds as $feed ) : ?>
					<option value="<?php echo esc_attr( $feed->ID ); ?>" <?php selected( $feed_id, $feed->ID ); ?>><?php echo esc_html( $feed->post_title ? $feed->post_title : sprintf( __( 'Feed #%d', 'bs-wp-ics-feed-reader' ), $feed->ID ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php if ( empty( $feeds ) ) : ?>
				<em class="description"><?php esc_html_e( 'Noch keine Feeds angelegt. Lege zuerst unter „ICS Feeds“ einen Kalender-Feed an.', 'bs-wp-ics-feed-reader' ); ?></em>
			<?php endif; ?>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'layout' ) ); ?>"><?php esc_html_e( 'Layout:', 'bs-wp-ics-feed-reader' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'layout' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'layout' ) ); ?>">
				<option value="list" <?php selected( $layout, 'list' ); ?>><?php esc_html_e( 'Liste (empfohlen für Sidebars)', 'bs-wp-ics-feed-reader' ); ?></option>
				<option value="grid" <?php selected( $layout, 'grid' ); ?>><?php esc_html_e( 'Kachel-Raster', 'bs-wp-ics-feed-reader' ); ?></option>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"><?php esc_html_e( 'Anzahl Termine:', 'bs-wp-ics-feed-reader' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'limit' ) ); ?>" type="number" min="1" max="50" value="<?php echo esc_attr( $limit ); ?>" />
		</p>
		<p>
			<input class="checkbox" type="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'only_future' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'only_future' ) ); ?>" <?php checked( $only_future ); ?> />
			<label for="<?php echo esc_attr( $this->get_field_id( 'only_future' ) ); ?>"><?php esc_html_e( 'Nur anstehende Termine anzeigen', 'bs-wp-ics-feed-reader' ); ?></label>
		</p>
		<p>
			<input class="checkbox" type="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'show_filter' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_filter' ) ); ?>" <?php checked( $show_filter ); ?> />
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_filter' ) ); ?>"><?php esc_html_e( 'Such-/Filterleiste anzeigen', 'bs-wp-ics-feed-reader' ); ?></label>
		</p>
		<p>
			<input class="checkbox" type="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'show_export' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_export' ) ); ?>" <?php checked( $show_export ); ?> />
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_export' ) ); ?>"><?php esc_html_e( '„In Kalender eintragen“-Buttons anzeigen', 'bs-wp-ics-feed-reader' ); ?></label>
		</p>
		<p class="description"><?php esc_html_e( 'Weitere Darstellungs- und Design-Optionen (Farben, Kachel-Stil …) werden aus den Feed-Einstellungen übernommen.', 'bs-wp-ics-feed-reader' ); ?></p>
		<?php
	}

	/**
	 * Validiert und bereinigt die übermittelten Widget-Einstellungen vor dem Speichern.
	 *
	 * @param array $new_instance Neu übermittelte Werte.
	 * @param array $old_instance Bisherige Werte.
	 * @return array Bereinigte Werte.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = [];

		$instance['title']       = isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['feed_id']     = isset( $new_instance['feed_id'] ) ? absint( $new_instance['feed_id'] ) : 0;
		$instance['layout']      = ( isset( $new_instance['layout'] ) && 'grid' === $new_instance['layout'] ) ? 'grid' : 'list';
		$instance['limit']       = isset( $new_instance['limit'] ) ? max( 1, min( 50, absint( $new_instance['limit'] ) ) ) : 5;
		$instance['only_future'] = ! empty( $new_instance['only_future'] );
		$instance['show_filter'] = ! empty( $new_instance['show_filter'] );
		$instance['show_export'] = ! empty( $new_instance['show_export'] );

		return $instance;
	}
}
