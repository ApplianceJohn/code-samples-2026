import Domodule from 'domodule';
import { addClass, findOne } from 'domassist';

class EventModal extends Domodule {
  postInit() {
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        this.close(document, event);
      }
    });

    const closeButton = findOne('[data-name="closeButton"]', this.el);
    if (closeButton) {
      closeButton.addEventListener('click', (event) => {
        this.close(closeButton, event);
        addClass(closeButton, 'hidden');
      });
    }
  }

  open() {
    this.els.mask.classList.add('event-modal__mask--visible');
  }

  close(el, event) {
    if (event.type === 'keydown' || el === event.target)
      this.els.mask.classList.remove('event-modal__mask--visible');
  }

  showCancellationPolicy(el, ev) {
    ev.preventDefault();
    alert(
      'We know that plans change, so if you need to cancel please let us know at least 96 hours prior to your reservation date to avoid a 50% cancellation fee. If you cancel within 24 hours of the date booked, you will be held responsible to pay the full amount.'
    );
  }
}

Domodule.register('EventModal', EventModal);