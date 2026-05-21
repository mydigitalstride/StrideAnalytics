<?php
/**
 * Client Updates: rich-text posts with user notifications and card previews.
 *
 * @package ECHoS_SEO_Analytics
 */

defined( 'ABSPATH' ) || exit;

class ECHS_Updates {

	const POST_TYPE       = 'echs_update';
	const NONCE_META      = 'echs_update_meta_nonce';
	const META_NOTIFY     = 'echs_update_notify_users';
	const META_NOTIFIED   = 'echs_update_notified';
	const PAGE_SLUG       = 'echs-updates';

	public static function init(): void {
		add_action( 'init',                        [ __CLASS__, 'register_post_type' ] );
		add_action( 'admin_menu',                  [ __CLASS__, 'register_menu' ] );
		add_action( 'add_meta_boxes',              [ __CLASS__, 'add_meta_boxes' ] );
		add_action( 'save_post_' . self::POST_TYPE, [ __CLASS__, 'save_meta' ], 10, 2 );
		add_action( 'admin_enqueue_scripts',       [ __CLASS__, 'maybe_enqueue_editor' ] );
	}

	public static function register_post_type(): void {
		register_post_type( self::POST_TYPE, [
			'labels'            => [
				'name'          => 'Client Updates',
				'singular_name' => 'Client Update',
				'add_new_item'  => 'Add New Update',
				'edit_item'     => 'Edit Update',
			],
			'public'            => false,
			'show_ui'           => false,
			'show_in_menu'      => false,
			'supports'          => [ 'title', 'editor', 'author' ],
			'capability_type'   => 'post',
			'map_meta_cap'      => true,
		] );
	}

	public static function register_menu(): void {
		add_submenu_page(
			'echs-settings',
			'Client Updates',
			'Client Updates',
			'manage_options',
			self::PAGE_SLUG,
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function add_meta_boxes(): void {
		add_meta_box(
			'echs_update_notify',
			'Notify Users',
			[ __CLASS__, 'render_notify_meta_box' ],
			self::POST_TYPE,
			'side',
			'default'
		);
	}

	public static function render_notify_meta_box( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_META, self::NONCE_META );
		$saved    = (array) get_post_meta( $post->ID, self::META_NOTIFY, true );
		$notified = (bool) get_post_meta( $post->ID, self::META_NOTIFIED, true );
		$users    = get_users( [ 'orderby' => 'display_name', 'order' => 'ASC' ] );

		echo '<p style="margin:0 0 8px;font-size:12px;color:#787c82;">Select users to notify by email when this update is published.</p>';
		echo '<div style="max-height:160px;overflow-y:auto;border:1px solid #dcdcde;padding:6px 8px;background:#fff;">';
		foreach ( $users as $user ) {
			$checked = in_array( $user->ID, $saved, true ) ? 'checked' : '';
			printf(
				'<label style="display:block;padding:3px 0;font-size:13px;">'
				. '<input type="checkbox" name="%s[]" value="%d" %s> %s</label>',
				esc_attr( self::META_NOTIFY ),
				$user->ID,
				$checked,
				esc_html( $user->display_name )
			);
		}
		echo '</div>';
		if ( $notified ) {
			echo '<p style="margin:8px 0 0;font-size:11px;color:#00a32a;">&#10003; Notification already sent for this update.</p>';
		}
	}

	public static function save_meta( int $post_id, WP_Post $post ): void {
		if (
			! isset( $_POST[ self::NONCE_META ] ) ||
			! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE_META ] ), self::NONCE_META )
		) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$user_ids = [];
		if ( ! empty( $_POST[ self::META_NOTIFY ] ) && is_array( $_POST[ self::META_NOTIFY ] ) ) {
			$user_ids = array_map( 'absint', $_POST[ self::META_NOTIFY ] );
		}
		update_post_meta( $post_id, self::META_NOTIFY, $user_ids );

		// Send notification only once, when status is publish and not yet notified.
		$already_notified = (bool) get_post_meta( $post_id, self::META_NOTIFIED, true );
		if ( 'publish' === $post->post_status && ! $already_notified && ! empty( $user_ids ) ) {
			self::send_notifications( $post_id, $post, $user_ids );
			update_post_meta( $post_id, self::META_NOTIFIED, true );
		}
	}

	private static function send_notifications( int $post_id, WP_Post $post, array $user_ids ): void {
		$author  = get_user_by( 'id', $post->post_author );
		$author_name = $author ? $author->display_name : get_bloginfo( 'name' );
		$date    = wp_date( 'F j, Y', strtotime( $post->post_date ) );
		$link    = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&view=' . $post_id );
		$subject = sprintf( 'New Update: %s', $post->post_title );

		$preview = self::content_to_preview( $post->post_content, 200 );

		$message = sprintf(
			"A new update has been posted:\n\n"
			. "%s\n"
			. "%s — %s\n\n"
			. "%s\n\n"
			. "Read & Comment: %s\n",
			$post->post_title,
			$author_name,
			$date,
			$preview,
			$link
		);

		foreach ( $user_ids as $uid ) {
			$user = get_user_by( 'id', $uid );
			if ( $user && ! empty( $user->user_email ) ) {
				wp_mail(
					$user->user_email,
					$subject,
					$message,
					[ 'Content-Type: text/plain; charset=UTF-8' ]
				);
			}
		}
	}

	/**
	 * Convert HTML update content to a plain-text preview.
	 *
	 * Converts <li> elements to "• item text" so bullet structure is
	 * preserved in the card preview rather than all text running together.
	 */
	public static function content_to_preview( string $html, int $max_chars = 120 ): string {
		// Replace closing block tags with a space so words don't merge.
		$text = preg_replace( '/<\/?(p|div|h[1-6]|blockquote|br)[^>]*>/i', ' ', $html );

		// Convert list items to "• text" entries separated by spaces.
		$text = preg_replace( '/<li[^>]*>(.*?)<\/li>/is', '• $1 ', $text );

		// Strip remaining tags.
		$text = wp_strip_all_tags( $text );

		// Collapse whitespace.
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		if ( mb_strlen( $text ) <= $max_chars ) {
			return $text;
		}

		return mb_substr( $text, 0, $max_chars ) . '…';
	}

	// ── Enqueue classic editor on the update new/edit screen ─────────────

	public static function maybe_enqueue_editor( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && $screen->post_type === self::POST_TYPE ) {
			wp_enqueue_editor();
		}
	}

	// ── Page renderer ─────────────────────────────────────────────────────

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.' ) );
		}

		$action  = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
		$view_id = isset( $_GET['view'] )   ? absint( $_GET['view'] )          : 0;
		$edit_id = isset( $_GET['edit'] )   ? absint( $_GET['edit'] )          : 0;
		$del_id  = isset( $_GET['delete'] ) ? absint( $_GET['delete'] )        : 0;

		// Handle delete.
		if ( $del_id && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'echs_delete_update_' . $del_id ) ) {
			wp_delete_post( $del_id, true );
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&deleted=1' ) );
			exit;
		}

		// Handle form submission (create / update).
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['echs_update_submit'] ) ) {
			self::handle_form_submission();
			return;
		}

		self::render_styles();

		if ( $view_id ) {
			self::render_single_view( $view_id );
		} elseif ( $edit_id || 'new' === $action ) {
			self::render_edit_form( $edit_id );
		} else {
			self::render_list();
		}
	}

	private static function handle_form_submission(): void {
		if ( ! isset( $_POST['_wpnonce_update_form'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce_update_form'] ), 'echs_update_form' ) ) {
			wp_die( 'Security check failed.' );
		}

		$post_id  = absint( $_POST['echs_update_id'] ?? 0 );
		$title    = sanitize_text_field( wp_unslash( $_POST['echs_update_title'] ?? '' ) );
		$content  = wp_kses_post( wp_unslash( $_POST['echs_update_content'] ?? '' ) );
		$user_ids = ! empty( $_POST[ self::META_NOTIFY ] ) && is_array( $_POST[ self::META_NOTIFY ] )
			? array_map( 'absint', $_POST[ self::META_NOTIFY ] )
			: [];

		if ( $post_id ) {
			// Editing existing.
			wp_update_post( [
				'ID'           => $post_id,
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'publish',
			] );
			update_post_meta( $post_id, self::META_NOTIFY, $user_ids );
		} else {
			// Creating new.
			$post_id = wp_insert_post( [
				'post_type'    => self::POST_TYPE,
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
			] );

			if ( $post_id && ! is_wp_error( $post_id ) ) {
				update_post_meta( $post_id, self::META_NOTIFY, $user_ids );

				// Send notifications immediately on creation.
				if ( ! empty( $user_ids ) ) {
					$post = get_post( $post_id );
					if ( $post ) {
						self::send_notifications( $post_id, $post, $user_ids );
						update_post_meta( $post_id, self::META_NOTIFIED, true );
					}
				}
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&saved=1' ) );
		exit;
	}

	private static function render_styles(): void {
		?>
		<style>
		#wpcontent { background: #0f1724; }
		.echs-updates-wrap { max-width: 900px; padding: 24px 0; }
		.echs-updates-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; }
		.echs-updates-header h1 { color:#fff; font-size:22px; margin:0; }
		.echs-updates-btn {
			display:inline-flex; align-items:center; gap:6px;
			background:#e8832a; color:#fff; font-weight:700;
			padding:8px 18px; border-radius:6px; text-decoration:none;
			font-size:13px; border:none; cursor:pointer;
		}
		.echs-updates-btn:hover { background:#d0721f; color:#fff; }
		.echs-update-card {
			background:#1a2234; border:1px solid #2a3550; border-radius:8px;
			padding:16px 20px; margin-bottom:12px;
			display:flex; align-items:center; gap:16px;
		}
		.echs-update-card-body { flex:1; min-width:0; }
		.echs-update-card-title { font-size:15px; font-weight:700; color:#fff; margin:0 0 4px; }
		.echs-update-card-meta { font-size:12px; color:#8899b4; margin:0 0 6px; }
		.echs-update-card-preview {
			font-size:13px; color:#c0cedf;
			white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
		}
		.echs-update-card-actions { display:flex; align-items:center; gap:8px; flex-shrink:0; }
		.echs-read-btn {
			background:#e8832a; color:#fff; font-weight:700;
			padding:7px 14px; border-radius:5px; text-decoration:none;
			font-size:12px; white-space:nowrap;
		}
		.echs-read-btn:hover { background:#d0721f; color:#fff; }
		.echs-comment-badge {
			display:inline-flex; align-items:center; gap:4px;
			background:#1e3a5f; color:#4db3ff; padding:5px 10px;
			border-radius:5px; font-size:12px; text-decoration:none;
		}
		.echs-icon-btn {
			background:#1a2234; border:1px solid #2a3550; color:#8899b4;
			padding:5px 9px; border-radius:5px; font-size:14px;
			text-decoration:none; cursor:pointer; display:inline-flex;
			align-items:center;
		}
		.echs-icon-btn:hover { color:#fff; border-color:#4db3ff; }
		.echs-icon-btn.echs-icon-delete:hover { color:#ff6b6b; border-color:#ff6b6b; }
		/* Single view */
		.echs-update-single { background:#1a2234; border:1px solid #2a3550; border-radius:8px; padding:28px 32px; }
		.echs-update-single-meta { font-size:13px; color:#8899b4; margin:0 0 20px; }
		.echs-update-single-content { color:#e0eaf5; font-size:14px; line-height:1.7; }
		.echs-update-single-content ul { padding-left:20px; }
		.echs-update-single-content li { margin-bottom:6px; }
		.echs-back-link { display:inline-block; margin-bottom:16px; color:#4db3ff; text-decoration:none; font-size:13px; }
		.echs-back-link:hover { text-decoration:underline; }
		/* Edit form */
		.echs-update-form { background:#1a2234; border:1px solid #2a3550; border-radius:8px; padding:28px 32px; }
		.echs-form-field { margin-bottom:20px; }
		.echs-form-label { display:block; color:#c0cedf; font-size:13px; font-weight:600; margin-bottom:6px; }
		.echs-form-input {
			width:100%; padding:9px 12px; background:#0f1724; border:1px solid #2a3550;
			color:#e0eaf5; border-radius:5px; font-size:14px; box-sizing:border-box;
		}
		.echs-form-input:focus { border-color:#4db3ff; outline:none; }
		.echs-form-textarea {
			width:100%; padding:9px 12px; background:#0f1724; border:1px solid #2a3550;
			color:#e0eaf5; border-radius:5px; font-size:14px; font-family:inherit;
			min-height:180px; box-sizing:border-box; resize:vertical;
		}
		.echs-notify-list { display:flex; flex-wrap:wrap; gap:8px; margin-top:6px; }
		.echs-notify-label {
			display:flex; align-items:center; gap:6px;
			background:#0f1724; border:1px solid #2a3550;
			color:#c0cedf; padding:5px 12px; border-radius:20px;
			font-size:12px; cursor:pointer;
		}
		.echs-notify-label:hover { border-color:#4db3ff; color:#fff; }
		.echs-notify-label input { margin:0; }
		.echs-notice { padding:10px 16px; border-radius:5px; margin-bottom:16px; font-size:13px; }
		.echs-notice-success { background:#0a2e1f; border:1px solid #00a32a; color:#00d65b; }
		.echs-empty { text-align:center; padding:60px 20px; color:#8899b4; }
		</style>
		<?php
	}

	private static function render_list(): void {
		$saved   = isset( $_GET['saved'] )   ? true : false;
		$deleted = isset( $_GET['deleted'] ) ? true : false;

		$posts = get_posts( [
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		?>
		<div class="wrap echs-updates-wrap">
			<?php if ( $saved ) : ?>
				<div class="echs-notice echs-notice-success">Update saved successfully.</div>
			<?php elseif ( $deleted ) : ?>
				<div class="echs-notice echs-notice-success">Update deleted.</div>
			<?php endif; ?>

			<div class="echs-updates-header">
				<h1>Client Updates</h1>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&action=new' ) ); ?>" class="echs-updates-btn">
					+ New Update
				</a>
			</div>

			<?php if ( empty( $posts ) ) : ?>
				<div class="echs-update-card echs-empty">
					<p>No updates yet. <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&action=new' ) ); ?>" style="color:#e8832a;">Create the first one</a>.</p>
				</div>
			<?php else : ?>
				<?php foreach ( $posts as $post ) :
					$author      = get_user_by( 'id', $post->post_author );
					$author_name = $author ? $author->display_name : '';
					$date_str    = wp_date( 'M j, Y', strtotime( $post->post_date ) );
					$preview     = self::content_to_preview( $post->post_content, 120 );
					$comments    = get_comments_number( $post->ID );
					$view_url    = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&view=' . $post->ID );
					$edit_url    = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&edit=' . $post->ID );
					$del_url     = wp_nonce_url(
						admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&delete=' . $post->ID ),
						'echs_delete_update_' . $post->ID
					);
				?>
				<div class="echs-update-card">
					<div class="echs-update-card-body">
						<p class="echs-update-card-title"><?php echo esc_html( $post->post_title ); ?></p>
						<p class="echs-update-card-meta"><?php echo esc_html( $date_str ); ?></p>
						<p class="echs-update-card-preview"><?php echo esc_html( $preview ); ?></p>
					</div>
					<div class="echs-update-card-actions">
						<a href="<?php echo esc_url( $view_url ); ?>" class="echs-read-btn">Read &amp; Comment &rsaquo;</a>
						<a href="<?php echo esc_url( $view_url ); ?>#comments" class="echs-comment-badge" title="Comments">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
							<?php echo absint( $comments ); ?>
						</a>
						<a href="<?php echo esc_url( $edit_url ); ?>" class="echs-icon-btn" title="Edit">&#9998;</a>
						<a href="<?php echo esc_url( $del_url ); ?>" class="echs-icon-btn echs-icon-delete" title="Delete"
							onclick="return confirm('Delete this update?');">&#128465;</a>
					</div>
				</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_single_view( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			echo '<div class="wrap"><p>Update not found.</p></div>';
			return;
		}

		$author      = get_user_by( 'id', $post->post_author );
		$author_name = $author ? $author->display_name : '';
		$date_str    = wp_date( 'F j, Y', strtotime( $post->post_date ) );

		?>
		<div class="wrap echs-updates-wrap">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ); ?>" class="echs-back-link">
				&larr; Back to Updates
			</a>
			<div class="echs-update-single">
				<p class="echs-update-single-meta">
					<?php echo esc_html( $date_str ); ?>
					<?php if ( $author_name ) : ?>
						&nbsp;&nbsp;by <?php echo esc_html( $author_name ); ?>
					<?php endif; ?>
				</p>
				<h2 style="color:#fff;margin:0 0 20px;"><?php echo esc_html( $post->post_title ); ?></h2>
				<div class="echs-update-single-content">
					<?php echo wp_kses_post( $post->post_content ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	private static function render_edit_form( int $post_id = 0 ): void {
		$post    = $post_id ? get_post( $post_id ) : null;
		$title   = $post ? $post->post_title   : '';
		$content = $post ? $post->post_content : '';
		$saved_notify = $post ? (array) get_post_meta( $post->ID, self::META_NOTIFY, true ) : [];
		$users   = get_users( [ 'orderby' => 'display_name', 'order' => 'ASC' ] );
		$heading = $post_id ? 'Edit Update' : 'New Update';

		?>
		<div class="wrap echs-updates-wrap">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ); ?>" class="echs-back-link">
				&larr; Back to Updates
			</a>
			<div class="echs-updates-header">
				<h1><?php echo esc_html( $heading ); ?></h1>
			</div>
			<div class="echs-update-form">
				<form method="post">
					<?php wp_nonce_field( 'echs_update_form', '_wpnonce_update_form' ); ?>
					<input type="hidden" name="echs_update_id" value="<?php echo absint( $post_id ); ?>">

					<div class="echs-form-field">
						<label class="echs-form-label" for="echs_update_title">Title</label>
						<input id="echs_update_title" name="echs_update_title" type="text"
							class="echs-form-input" value="<?php echo esc_attr( $title ); ?>" required>
					</div>

					<div class="echs-form-field">
						<label class="echs-form-label" for="echs_update_content">Content</label>
						<p style="font-size:12px;color:#8899b4;margin:0 0 6px;">
							Use the toolbar to add bullet points. Bullets will display in both the full view and card preview.
						</p>
						<?php
						wp_editor( $content, 'echs_update_content', [
							'textarea_name' => 'echs_update_content',
							'media_buttons' => false,
							'teeny'         => false,
							'textarea_rows' => 10,
							'editor_class'  => 'echs-form-textarea',
						] );
						?>
					</div>

					<div class="echs-form-field">
						<label class="echs-form-label">Notify Users</label>
						<p style="font-size:12px;color:#8899b4;margin:0 0 8px;">
							Selected users will receive an email notification when this update is created.
						</p>
						<div class="echs-notify-list">
							<?php foreach ( $users as $user ) :
								$checked = in_array( $user->ID, $saved_notify, true ) ? 'checked' : '';
							?>
							<label class="echs-notify-label">
								<input type="checkbox" name="<?php echo esc_attr( self::META_NOTIFY ); ?>[]"
									value="<?php echo absint( $user->ID ); ?>" <?php echo $checked; ?>>
								<?php echo esc_html( $user->display_name ); ?>
							</label>
							<?php endforeach; ?>
						</div>
					</div>

					<button type="submit" name="echs_update_submit" class="echs-updates-btn">
						<?php echo $post_id ? 'Save Changes' : 'Publish Update'; ?>
					</button>
				</form>
			</div>
		</div>
		<?php
	}
}
