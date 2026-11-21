import Domodule from 'domodule';
import MultistepForm from './multistepForm';
import { findOne, removeClass } from 'domassist';

import WPREST from './wpRestFunctions';

export default class EventMultiForm extends MultistepForm {
  constructor(element) {
    super(element);
    this.confirmationUrl = '/wp-json/client/v1/events/confirmation';
    this.eventType = 'event-reservation';
  }

  updateFormSections() {
    this.formSections.forEach(async (section, index) => {
      if (index + 1 === this.currentStep) {
        section.classList.remove('hidden');

        if (section.classList.contains('reserve-summary')) {
          await this.reserveSummarySection(section);
        }
      } else {
        section.classList.add('hidden');
      }
    });
    this.updateNextButtonVisibility();
  }

  async fetchTicketData() {
    const formFields = findOne(`#${this.el.id}`).elements;

    const reserveParams = new URLSearchParams({
      date: this.formatDate(formFields['event_date'].value, 'Y-m-d'),
      size: formFields['ticket_quantity'].value,
      guestType: formFields['ticket_type'].value,
    });

    const reserveResponse = await WPREST.getFromREST(
      reserveParams,
      'events/check'
    );

    if (reserveResponse.length === 0) {
      alert('This event has sold out.');
      this.goToStep(1);
      return false;
    }

    formFields['booking_code'].value = reserveResponse.bookingCode;

    reserveResponse.guestTypes.forEach((guestType) => {
      if (guestType.guestType === formFields['ticket_type'].value) {
        formFields['ticket_price'].value = guestType.price.substring(1);
      }
    });

    return true;
  }

  async reserveSummarySection(section) {
    const checkTickets = await this.fetchTicketData();
    if (!checkTickets) return;

    const formFields = findOne(`#${this.el.id}`).elements;

    const summaryFieldmap = {
      reservename: findOne('.summary-reservename', section),
      email: findOne('.summary-email', section),
      date: findOne('.summary-date', section),
      number: findOne('.summary-number', section),
      price: findOne('.summary-price', section),
    };

    if (parseInt(formFields['ticket_price'].value) !== 0) {
      summaryFieldmap.fullname = findOne('.summary-fullname', section);
      summaryFieldmap.address = findOne('.summary-address', section);
      summaryFieldmap.citystatezip = findOne('.summary-citystatezip', section);
      summaryFieldmap.phone = findOne('.summary-phone', section);

      this.els.wordpayIframe.replaceChildren();
    }

    summaryFieldmap.reservename.textContent =
      formFields['first_name'].value + ' ' + formFields['last_name'].value;
    summaryFieldmap.email.textContent = formFields.email.value;
    summaryFieldmap.date.textContent = formFields['event_date'].value;
    summaryFieldmap.number.textContent = formFields['ticket_quantity'].value;
    summaryFieldmap.price.textContent = (
      formFields['ticket_price'].value * formFields['ticket_quantity'].value
    ).toFixed(2);
    if (parseInt(formFields['ticket_price'].value) !== 0) {
      summaryFieldmap.phone.textContent = formFields['phone_number'].value;
      summaryFieldmap.fullname.textContent =
        formFields['billing_first_name'].value +
        ' ' +
        formFields['billing_last_name'].value;
      summaryFieldmap.address.textContent =
        formFields['billing_address_1'].value +
        ' ' +
        formFields['billing_address_2'].value;
      summaryFieldmap.citystatezip.textContent =
        formFields['billing_city'].value +
        ', ' +
        formFields['billing_state'].value +
        ' ' +
        formFields['billing_zip'].value;
    }

    const userParams = new URLSearchParams({
      reserveId: formFields['user_id'].value,
      date: this.formatDate(formFields['event_date'].value, 'Ymd'),
      eventId: formFields['event_id'].value,
    });

    const userResponse = await WPREST.getFromREST(userParams, 'events/user');

    if (
      parseInt(formFields['ticket_quantity'].value) + userResponse[1] >
      formFields['event_max'].value
    ) {
      alert(
        `You have exceeded the number of tickets available to you for this event. If you believe you have received this message in error, please contact Guest Services at (888) 888-8888 or GuestServices@Client.com for further assistance.`
      );
      this.goToStep(1);
      return;
    }

    const reserveParams = new URLSearchParams({
      date: this.formatDate(formFields['event_date'].value, 'Y-m-d'),
      size: formFields['ticket_quantity'].value,
      guestType: formFields['ticket_type'].value,
    });

    const reserveResponse = await WPREST.getFromREST(
      reserveParams,
      'events/check'
    );

    if (reserveResponse.length === 0) {
      alert('This event has sold out.');
      this.goToStep(1);
      return;
    }

    this.userPostData = {
      userId: formFields['user_id'].value,
      firstName: formFields['first_name'].value,
      lastName: formFields['last_name'].value,
      email: formFields['email'].value,
      phone: this.phoneInput.getNumber(),
      termsAccept: true,
      amount:
        formFields['ticket_price'].value * formFields['ticket_quantity'].value,
      bookingCode: formFields['booking_code'].value,
      notes: '',
      optIn: formFields['mailingList'].checked,
      guestType: formFields['ticket_type'].value,
      size: formFields['ticket_quantity'].value,
      date: formFields['event_date'].value,
      eventId: formFields['event_id'].value,
    };

    if (parseInt(formFields['ticket_price'].value) !== 0) {
      this.userPostData = {
        ...this.userPostData,
        address1: formFields['billing_address_1'].value,
        address2: formFields['billing_address_2'].value,
        city: formFields['billing_city'].value,
        state: formFields['billing_state'].value,
        zip: formFields['billing_zip'].value,
      };

      WPREST.postToREST(this.userPostData, 'reserve/process-payment').then(
        (response) => {
          this.userPostData = { ...this.userPostData, ...response['args'] };
          const iframe = document.createElement('iframe');
          this.iframeId = `worldpay-iframe-${this.userPostData.bookingCode}`;
          iframe.id = this.iframeId;
          iframe.src = response['url'];
          iframe.style = 'width:100%;height:100%;min-height:300px;';
          this.els.wordpayIframe.replaceChildren(iframe);

          window.addEventListener('message', (e) => {
            if (
              e.data.name === 'worldpayResponse' &&
              e.source === iframe.contentWindow
            ) {
              if (e.data.status) {
                if (e.data?.transaction?.HostedPaymentStatus === 'Cancelled') {
                  this.handlePrevButtonClick();
                  this.els.wordpayIframe.replaceChildren(
                    document.createElement('div')
                  );
                  return;
                }

                // DUPLICATE CHECK
                if (this.vantivSuccess) return;
                this.vantivSuccess = true;

                WPREST.postToREST(
                  {
                    ...this.userPostData,
                    ...e.data.transaction,
                    eventType: this.eventType,
                  },
                  'reserve/book'
                ).then(
                  (success) => {
                    const response = JSON.parse(success.body);
                    if (response?.confirmationNumber) {
                      const iframe = document.getElementById(this.iframeId);
                      const closeButton = findOne('.close-button', this.el);
                      iframe.src = this.confirmationUrl;
                      if (closeButton) removeClass(closeButton, 'hidden');
                    }
                  },
                  (failure) => {
                    console.error('RESERVATION FAILURE: ', failure);
                  }
                );
              } else {
                alert('Payment failed. Please try again.');
              }
            }
          });
        },
        (error) => {
          console.error('VANTIV ERROR: ' + error);
        }
      );
    }
  }

  submitFreeBooking() {
    this.els.reserveButton.innerHTML = 'Processing...';
    this.els.reserveButton.disabled = true;
    WPREST.postToREST(
      {
        ...this.userPostData,
        eventType: this.eventType,
      },
      'reserve/book'
    ).then(
      (success) => {
        const response = JSON.parse(success.body);
        if (response?.confirmationNumber) {
          this.els.reserveButton.style.display = 'none';
          this.els.successNotice.style.display = '';
          const closeButton = findOne('.close-button', this.el);
          if (closeButton) removeClass(closeButton, 'hidden');
        }
      },
      (failure) => {
        console.error('RESERVATION FAILURE: ', failure);
      }
    );
  }

  formatDate(dateStr, format) {
    let dateObj = new Date(dateStr);

    let month = ('0' + (dateObj.getMonth() + 1)).slice(-2);
    let day = ('0' + dateObj.getDate()).slice(-2);
    let year = dateObj.getFullYear();

    if (format === 'Y-m-d') return `${year}-${month}-${day}`;
    if (format === 'Ymd') return `${year}${month}${day}`;
  }

  resetForm() {
    this.goToStep(1);
    this.els.wordpayIframe.replaceChildren(document.createElement('div'));
    if (this.els.successNotice) {
      this.els.successNotice.style.display = 'none';
    }
  }
}

Domodule.register('EventMultiForm', EventMultiForm);