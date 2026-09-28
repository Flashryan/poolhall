import { dismissToast, useStore } from '../store';
import { Icon } from './Icons';

export function Toasts() {
	const toasts = useStore( ( s ) => s.toasts );
	return (
		<div className="mnafb-toasts" aria-live="polite" aria-atomic="false">
			{ toasts.map( ( t ) => (
				<div key={ t.id } className={ `mnafb-toast mnafb-toast--${ t.tone }` } role={ t.tone === 'error' ? 'alert' : 'status' }>
					<Icon name={ t.tone === 'error' ? 'warning' : t.tone === 'success' ? 'check' : 'message' } size={ 16 } />
					<span>{ t.text }</span>
					{ t.action && (
						<button
							type="button"
							className="mnafb-toast__action"
							onClick={ () => {
								t.action?.run();
								dismissToast( t.id );
							} }
						>
							{ t.action.label }
						</button>
					) }
					<button type="button" className="mnafb-toast__close" aria-label="Dismiss" onClick={ () => dismissToast( t.id ) }>
						<Icon name="close" size={ 14 } />
					</button>
				</div>
			) ) }
		</div>
	);
}
