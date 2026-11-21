/*global user_id*/
/*global book_mode*/
import Domodule from 'domodule';
import { findOne, removeClass } from 'domassist';
import intlTelInput from 'intl-tel-input';

import WPREST from './wpRestFunctions';

export default class MultistepForm extends Domodule {
  constructor(element) {
    super(element);
    this.confirmationUrl = '/wp-json/client/v1/reserve/confirmation';
    this.currentStep = 1;
    this.iframeId = '';
    this.totalSteps = this.el.dataset.totalSteps ?? 3;
    this.nextButtons = this.el.querySelectorAll('.next-button');
    this.prevButtons = this.el.querySelectorAll('.prev-button');
    this.formSections = this.el.querySelectorAll('.form-section');
    this.formFields = this.el.querySelectorAll('input, select, textarea');
    this.userPostData = {};
    this.eventType = 'reservation';
    this.vantivSuccess = false;
    this.setupForm();
  }

  setupForm() {
    const phoneInput = findOne('.phone_number', this.el);
    this.phoneInput = intlTelInput(phoneInput, {
      initialCountry: 'us',
      utilsScript:
        'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/19.5.7/js/utils.min.js',
    });

    this.nextButtons.forEach((button) => {
      button.addEventListener('click', this.handleNextButtonClick.bind(this));
    });
    this.prevButtons.forEach((button) => {
      button.addEventListener('click', this.handlePrevButtonClick.bind(this));
    });
    this.formFields.forEach((field) => {
      field.addEventListener(
        'change',
        this.updateNextButtonVisibility.bind(this)
      );
    });
  }

  updateNextButtonVisibility() {
    const currentSection = this.formSections[this.currentStep - 1];
    const inputs = currentSection.querySelectorAll('input, select, textarea');
    const isValid = Array.from(inputs).every((input) => {
      if (input.classList.contains('phone_number')) {
        return this.phoneInput.isValidNumber();
      } else {
        return 'hidden' !== input.type
          ? input.checkValidity()
          : input.value.length > 0;
      }
    });
    this.nextButtons.forEach((button) => {
      button.style.display = isValid ? '' : 'none';
      if (isValid) {
        button.removeAttribute('disabled');
        button.removeAttribute('aria-disabled');
      } else {
        button.setAttribute('disabled', 'true');
        button.setAttribute('aria-disabled', 'true');
      }
    });
  }

  handleNextButtonClick() {
    if (this.currentStep < this.totalSteps) {
      switch (this.currentStep) {
        case 2:
          if (this.checkContactErrors()) {
            return;
          }
          break;
        case 3:
          if (this.checkBillingErrors()) {
            return;
          }
          break;
        default:
          break;
      }
      this.goToStep(this.currentStep + 1);
    }
  }

  handlePrevButtonClick() {
    if (this.currentStep > 1) {
      this.goToStep(this.currentStep - 1);
    }
  }

  goToStep(step) {
    this.currentStep = step;
    this.toggleError(false, '');
    this.updateFormSections();
  }

  updateFormSections() {
    this.formSections.forEach(async (section, index) => {
      if (index + 1 === this.currentStep) {
        section.classList.remove('hidden');

        if (section.id === 'reserve-summary') {
          const formFields = findOne('#reserve-form').elements;
          const summaryFieldmap = {
            reservename: findOne('#summary-reservename', section),
            email: findOne('#summary-email', section),
            date: findOne('#summary-date', section),
            number: findOne('#summary-number', section),
            price: findOne('#summary-price', section),
            fullname: findOne('#summary-fullname', section),
            address: findOne('#summary-address', section),
            citystatezip: findOne('#summary-citystatezip', section),
            phone: findOne('#summary-phone', section),
          };

          this.els.wordpayIframe.replaceChildren();

          summaryFieldmap.reservename.textContent =
            formFields['first_name'].value +
            ' ' +
            formFields['last_name'].value;
          summaryFieldmap.email.textContent = formFields.email.value;
          summaryFieldmap.date.textContent = formFields['reserved-dates'].value;
          summaryFieldmap.number.textContent = formFields.productcount.value;
          summaryFieldmap.price.textContent =
            formFields['product-price'].value * formFields.productcount.value;
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
          summaryFieldmap.phone.textContent = formFields['phone_number'].value;

          const userParams = new URLSearchParams({
            reserveId: user_id,
            date: formFields['reserved-dates'].value,
          });

          const userResponse = await WPREST.getFromREST(
            userParams,
            'reserve/user'
          );

          console.log(userResponse);

          if (userResponse[1] + parseInt(formFields.productcount.value) > 2) {
            if (book_mode === 'members') {
              alert(
                'Reservations are currently limited to 2 products per person. Additional reservations can be made starting Thursday.'
              );
              this.goToStep(1);
              return;
            }
            alert(
              'You cannot have more than two reservations per day. Please select a product count of one or contact us for additional reservations.'
            );
            this.goToStep(1);
            return;
          }

          const reserveParams = new URLSearchParams({
            date: formFields['reserved-dates'].value,
            size: formFields.productcount.value,
          });

          const reserveResponse = await WPREST.getFromREST(
            reserveParams,
            'reserve/check'
          );

          if (reserveResponse.length === 0) {
            alert('This date is not available. Please choose another date.');
            this.goToStep(1);
            return;
          }

          this.userPostData = {
            userId: user_id,
            firstName: formFields['first_name'].value,
            lastName: formFields['last_name'].value,
            email: formFields['email'].value,
            phone: this.phoneInput.getNumber(),
            address1: formFields['billing_address_1'].value,
            address2: formFields['billing_address_2'].value,
            city: formFields['billing_city'].value,
            state: formFields['billing_state'].value,
            zip: formFields['billing_zip'].value,
            termsAccept: true,
            amount:
              formFields['product-price'].value * formFields.productcount.value,
            bookingCode: formFields['booking_code'].value,
            notes: formFields['notes'].value,
            optIn: formFields['mailingList'].checked,
            guestType: formFields['guest_type'].value,
            size: formFields.productcount.value,
            date: formFields['reserved-dates'].value,
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
                    if (
                      e.data?.transaction?.HostedPaymentStatus === 'Cancelled'
                    ) {
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
      } else {
        section.classList.add('hidden');
      }
    });
    this.updateNextButtonVisibility();
  }

  checkContactErrors() {
    let errorMessages = {
      contactFirstName: 'Please enter a valid Contact First Name',
      contactLastName: 'Please enter a valid Contact Last Name',
      email: 'Please enter a valid Contact Email Address',
      phone: 'Please enter a valid Contact Phone Number',
    };
    let data = {
      contactFirstName: findOne('.first_name', this.el).value,
      contactLastName: findOne('.last_name', this.el).value,
      email: findOne('.email', this.el).value,
      phone: findOne('.phone_number', this.el).value,
    };

    let errors = [];

    for (const [idx, field] of Object.entries(data)) {
      if (field === '' || field === false) {
        errors.push(errorMessages[idx]);
      }
    }

    if (errors.length > 0) {
      this.toggleError(true, errors.join('<br />'));
      return true;
    }

    return false;
  }

  checkBillingErrors() {
    let errorMessages = {
      firstName: 'Please enter a valid Billing First Name',
      lastName: 'Please enter a valid Billing Last Name',
      address1: 'Please enter a valid Billing Address 1',
      city: 'Please enter a valid Billing City',
      state: 'Please select a valid Billing State',
      zip: 'Please enter a valid Billing Zip Code',
      termsAccept: 'Please read and accept the Terms of Use',
    };
    let data = {
      firstName: findOne('.billing_first_name', this.el).value,
      lastName: findOne('.billing_last_name', this.el).value,
      address1: findOne('.billing_address_1', this.el).value,
      city: findOne('.billing_city', this.el).value,
      state: findOne('.billing_state', this.el).value,
      zip: findOne('.billing_zip', this.el).value,
      termsAccept: findOne('.termsOfUse', this.el).checked,
    };

    let errors = [];

    for (const [idx, field] of Object.entries(data)) {
      if (field === '' || field === false) {
        errors.push(errorMessages[idx]);
      }
    }

    if (errors.length > 0) {
      this.toggleError(true, errors.join('<br />'));
      return true;
    }

    return false;
  }

  toggleError(show, text) {
    let errorsBox = findOne('.errorsBox', this.el);
    let errorsBody = errorsBox.querySelector('.body');

    if (show) {
      errorsBody.innerHTML = text;
      errorsBox.classList.remove('hidden');
    } else {
      errorsBox.classList.add('hidden');
      errorsBody.innerHTML = '';
    }
  }
}

Domodule.register('MultistepForm', MultistepForm);