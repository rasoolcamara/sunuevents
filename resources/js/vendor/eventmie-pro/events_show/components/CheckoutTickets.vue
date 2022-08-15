<template>
    <div>
      <div class="modal modal-mask" v-if="showModal">
        <div class="modal-dialog modal-container modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" @click="close()"><span aria-hidden="true">&times;</span></button>
              <div class="text-center">
                <h3 class="title ticket-selected-text mb-4">{{ trans('em.checkout') }}</h3>
              </div>
            </div>

            <form ref="form" @submit.prevent="" method="POST" >
              <div class="form-group row">
                <label for="full_name" class="col-sm-2 my-0">Nom Complet</label>
                <div class="col-sm-10">
                  <input type="text" class="form-control" id="full_name" name="full_name" v-model="full_name" v-validate="'required'">
                </div>
              </div>
              <div class="form-group row">
                <label for="phone" class="col-sm-2 my-0">Téléphone</label>
                <div class="col-sm-10">
                  <input type="text" class="form-control" id="phone" name="phone" v-model="phone" v-validate="'required'">
                </div>
              </div>

              <div class="row" >

                <!-- Cart Totals -->
                <div class="col-md-12">
                  <p class="m-0 lead lead-caption text-center">{{ trans('em.cart') }}</p>

                  <ul class="list-group m-0">
                    <li class="list-group-item d-flex justify-content-between">
                      <h6 class="my-0"><strong>{{ trans('em.total_tickets') }}</strong></h6>
                      <strong :class="{'ticket-selected-text': bookedTicketsTotal() > 0 }">{{ bookedTicketsTotal() }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                      <h6 class="my-0"><strong>{{ trans('em.total_order') }}</strong></h6>
                      <strong :class="{'ticket-selected-text': bookedTicketsTotal() > 0 }">{{ total }} <small>{{currency}}</small></strong>
                    </li>
                  </ul>
                </div>

                <!-- If not logged in -->
                <!--<div class="col-md-12" v-if="!login_user_id">
                  <div class="alert alert-danger">
                    {{ trans('em.please_login_signup') }}
                  </div>
                </div>-->

                <!-- Payments -->
                <div class="col-md-12" v-if="bookedTicketsTotal() > 0">

                  <p class="m-0 lead lead-caption text-center ">{{ trans('em.payment') }}</p>

                  <!-- Free -->
                  <div class="d-block my-3 pl-3" v-if="total <= 0">
                    <div class="radio-inline">
                      <input id="free_order" name="free_order" type="radio" class="custom-control-input" checked>
                      <label class="custom-control-label" for="free_order"> &nbsp;<i class="fas fa-glass-cheers"></i> {{ trans('em.free') }} <small class="text-lowercase">({{ trans('em.checkout') }}-{{ trans('em.free') }} )</small></label>
                    </div>
                  </div>

                  <!-- Paid -->
                  <div class="d-block my-3 pl-3" v-else>

                    <!-- For Organizer & Customer -->
                    <div class="radio-inline" v-if="is_admin <= 0 && is_paypal > 0">
                      <input type="radio" class="custom-control-input" id="payment_method_paypal" name="payment_method" v-model="payment_method" value="1" >
                      <label class="custom-control-label" for="payment_method_paypal"> &nbsp;<i class="fab fa-paypal"></i> PayPal</label>
                    </div>

                    <!-- CUSTOM -->
                    <!-- For Organizer & Customer -->
                    <!-- WAVE -->
                    <div class="col-md-2 col-xs-12" v-if="is_admin <= 0 ">
                      <input type="radio" class="custom-control-input" id="payment_method_WAVE" name="payment_method" v-model="payment_method" value="3">
                      <!--<label class="custom-control-label" for="payment_method_WAVE"> &nbsp;Wave</label>-->
                      <img class="w-50" :src="require('../../../../../../public/images/wave.png').default" alt="wave"/>
                    </div>
                    <!-- OM SN -->

                    <div class=" col-md-2 col-xs-12" v-if="is_admin <= 0 ">
                      <input type="radio" class="custom-control-input" id="payment_method_OM" name="payment_method" v-model="payment_method" value="4">
                      <img class="w-50" :src="require('../../../../../../public/images/orange_money.png').default" alt="wave"/>
                    </div>
                    <!-- PAYDUNYA -->

                    <div class="col-md-2 col-xs-12 mt-4" v-if="is_admin <= 0 ">
                      <input type="radio" class="custom-control-input" id="payment_method_Paydunia" name="payment_method" v-model="payment_method" value="2">
                      <img class="w-50" :src="require('../../../../../../public/images/visa_mastercard.png').default" alt="wave"/>
                    </div>
                    <!-- CUSTOM -->


                    <!-- For Admin & Organizer & Customer -->
                    <div class="radio-inline"
                         v-if="
                                                (is_organiser > 0 && is_offline_payment_organizer > 0) ||
                                                (is_customer > 0 && is_offline_payment_customer > 0) ||
                                                (is_admin > 0)
                                            "
                    >
                      <input type="radio" class="custom-control-input" id="payment_method_offline" name="payment_method" v-model="payment_method" value="offline">
                      <label class="custom-control-label" for="payment_method_offline"> &nbsp;<i class="fas fa-suitcase-rolling"></i> {{ trans('em.offline') }} <small>({{ trans('em.cash_on_arrival') }})</small></label>
                    </div>
                  </div>

                </div>

              </div>

              <div class="row mt-5">
                <div class="col-xs-12">
                  <button :class="{ 'disabled' : disable }"  :disabled="disable" type="button" class="btn lgx-btn btn-block" @click="bookTickets"><i class="fas fa-cash-register"></i> {{ trans('em.checkout') }}</button>
                </div>
                <!-- <div class="col-xs-12">
                   <div class="btn-group btn-group-justified">
                     <button type="button" class="btn lgx-btn w-50" @click="signupFirst()"><i class="fas fa-user-plus"></i> {{ trans('em.register') }}</button>
                     <button type="button" class="btn lgx-btn lgx-btn-black w-50" @click="loginFirst()"><i class="fas fa-fingerprint"></i> {{ trans('em.login') }}</button>
                   </div>
                 </div>-->
              </div>

            </form>
            <form action="https://api.paiementorangemoney.com" method="POST" ref="form_om"  >
              <input type="hidden" name="S2M_IDENTIFIANT" :value="om_config.identifiant">
              <input type="hidden" name="S2M_SITE" :value="om_config.site">
              <input type="hidden" name="S2M_TOTAL" :value="200">
              <input type="hidden" name="S2M_REF_COMMANDE" :value="om_config.ref_commande">
              <input type="hidden" name="S2M_COMMANDE" :value="om_config.commande">
              <input type="hidden" name="S2M_DATEH" :value="om_config.dateh">
              <input type="hidden" name="S2M_HTYPE" :value="om_config.algo">
              <input type="hidden" name="S2M_HMAC" :value="om_config.hmac">
            </form>
          </div>
        </div>
      </div>



    </div>

</template>

<script>

import { mapState, mapMutations} from 'vuex';
import mixinsFilters from '../../../../../../eventmie-pro/resources/js/mixins.js';
import _ from 'lodash';
import CheckoutTicket from './CheckoutTickets';

export default {

  mixins:[
    mixinsFilters
  ],
  components: {
    'checkout-ticket'    : CheckoutTicket
  },

  props : [
    'tickets',
    'max_ticket_qty',
    'event',
    'currency',
    'login_user_id',
    'is_admin',
    'is_organiser',
    'is_customer',
    'is_paypal',
    'is_offline_payment_organizer',
    'is_offline_payment_customer',
    'booked_tickets'
  ],

  data() {
    return {
      showModal           : false,
      openModal           : false,
      ticket_info         : false,
      moment              : moment,
      quantity            : [1],
      price               : null,
      total_price         : [],
      customer_id         : 0,
      full_name           : null,
      phone               : null,
      total               : 0,
      disable             : false,
      payment_method      : 'offline',
      om_config           : {
        identifiant       : null,
        site              : null,
        dateh             : null,
        algo              : null,
        ref_commande      : null ,
        total             : null,
        commande          : null,
        hmac              : null
      },

      // customers options
      options             : [],
      //selected customer
      customer            : null,

      /* wave: require('../../../../../../public/images/wave.png'),*/

    }
  },

  computed: {
    // get global variables
    ...mapState( ['booking_date', 'start_time', 'end_time', 'booking_end_date', 'booked_date_server']),
  },

  methods: {
    // update global variables
    ...mapMutations(['add', 'update']),

    // reset form and close modal
    close: function () {
      this.price          = null;
      this.quantity       = [];
      this.total_price    = [];

      this.add({
        booking_date        : null,
        booked_date_server  : null,
        booking_end_date    : null,
        start_time          : null,
        end_time            : null,
      })


      this.openModal      = false;
    },
    checkout(){
      let post_data = new FormData(this.$refs.formTest);
      console.log(this.event);
      //this.showModal      = true;
    },
    bookTickets(){
      // show loader
      this.showLoaderNotification(trans('em.processing'));

      // prepare form data for post request
      this.disable = true;

      let post_url = route('eventmie.bookings_book_tickets');

      let post_data = new FormData(this.$refs.form);

      // axios post request
      axios.post(post_url, post_data)
          .then(res => {

            /*  if(res.data.status && res.data.message != ''  && typeof(res.data.message) != "undefined") {

                // hide loader
                  Swal.hideLoading();
                // close popup
                  this.close();
                  this.showNotification('success', res.data.message);

              }
              else if(!res.data.status && res.data.message != '' && res.data.url != ''  && typeof(res.data.url) != "undefined"){

                // hide loader
                Swal.hideLoading();

                // close popup
                this.close();
                this.showNotification('error', res.data.message);

                setTimeout(() => {
                  window.location.href = res.data.url;
                }, 1000);
              }

              if(res.data.url != '' && res.data.status  && typeof(res.data.url) != "undefined") {

                // hide loader
                Swal.hideLoading();

                setTimeout(() => {
                  window.location.href = res.data.url;
                }, 1000);
              }

              if(res.data.status && res.data.om_config != '') {

                this.om_config.identifiant = res.data.om_config.identifiant;
                this.om_config.site = res.data.om_config.site;
                this.om_config.dateh = res.data.om_config.dateh;
                this.om_config.total = res.data.om_config.total;
                this.om_config.ref_commande = res.data.om_config.ref_commande;
                this.om_config.algo = res.data.om_config.algo;
                this.om_config.commande = res.data.om_config.commande;
                this.om_config.hmac = res.data.om_config.hmac;

                return  res;

              }

              if(!res.data.status && res.data.message != ''  && typeof(res.data.message) != "undefined") {

                // hide loader
                Swal.hideLoading();
                // close popup
                this.close();
                this.showNotification('error', res.data.message);
              }

            }).then(res => {
              if(res.data.status && res.data.om_config) {
                this.$refs.form_om.submit();
              }*/
          }).catch(error => {
        this.disable = false;
        let serrors = Vue.helpers.axiosErrors(error);
        if (serrors.length) {

          this.serverValidate(serrors);

        }
      });
    },


    // validate data on form submit
    validateForm(e) {
      this.$validator.validateAll().then((result) => {
        if (result) {
          this.disable = true;
          this.formSubmit(e);
        }
        else{
          this.disable = false;
        }
      });
    },

    // show server validation errors
    serverValidate(serrors) {
      this.disable = false;
      this.$validator.validateAll().then((result) => {
        this.$validator.errors.add(serrors);
      });
    },


    // count total tax
    countTax(price, tax, rate_type, net_price, quantity) {

      price           = parseFloat(price).toFixed(2);
      tax             = parseFloat(tax).toFixed(2);
      var total_tax   = parseFloat(quantity * tax).toFixed(2);


      // in case of percentage
      if(rate_type == 'percent')
      {
        if(isNaN((price * total_tax)/100))
          return 0;

        total_tax = (parseFloat((price*total_tax)/100)).toFixed(2);

        if(net_price == 'excluding')
          return total_tax+' '+this.currency+' ('+tax+'%'+' '+trans('em.exclusive')+')';
        else
          return total_tax+' '+this.currency+' ('+tax+'%'+' '+trans('em.inclusive')+')';
      }

      // for fixed tax
      if(rate_type == 'fixed')
      {
        if(net_price == 'excluding')
          return total_tax+' '+this.currency+' ('+tax+' '+this.currency+' '+trans('em.exclusive')+')';
        else
          return total_tax+' '+this.currency+' ('+tax+' '+this.currency+' '+trans('em.inclusive')+')';
      }

      return 0;
    },

    // count total price
    totalPrice(){
      if(this.quantity != null || this.quantity.length > 0)
      {
        let amount;
        let tax;
        let total_tax ;
        this.quantity.forEach(function(value, key) {
          total_tax               = 0;
          this.total_price[key]   = [];

          amount                  = (parseFloat(value * this.tickets[key].price)).toFixed(2);

          // when have no taxes set set total_price with actual ammount without taxes
          if(Object.keys(this.total_price).length > 0)
          {
            this.total_price.forEach(function(v, k){

              if(Object.keys(v).length <= 0);
              this.total_price[key] = amount;

            }.bind(this))
          }
          if(this.tickets[key].taxes.length > 0 && amount > 0) {

            this.tickets[key].taxes.forEach(function(tax_v, tax_k) {
              // in case of percentage
              if(tax_v.rate_type == 'percent')
              {
                // in case of excluding
                if(tax_v.net_price == 'excluding')
                {
                  tax = isNaN((amount * tax_v.rate)/100) ? 0 : (parseFloat((amount*tax_v.rate)/100)).toFixed(2);

                  total_tax   =  parseFloat(total_tax) + parseFloat(tax);
                }
              }

              // // in case of percentage
              if(tax_v.rate_type == 'fixed')
              {
                tax   = parseFloat(value *tax_v.rate);

                // // in case of excluding
                if(tax_v.net_price == 'excluding')
                  total_tax   = parseFloat(total_tax) + parseFloat(tax);

              }

            }.bind(this))
          }

          this.total_price[key] = (parseFloat(amount) + parseFloat(total_tax)).toFixed(2);

        }.bind(this));
      }
    },

    updateItem() {
      this.$emit('changeItem');
    },

    setDefaultQuantity() {
      // only set default value once
      var _this   = this;
      var promise = new Promise(function(resolve, reject) {
        // only set default value once
        if(_this.quantity.length == 1) {
          _this.tickets.forEach(function(value, key) {
            if(key == 0)
              _this.quantity[key] = 0;
            else
              _this.quantity[key] = 0;

          }.bind());
        }
        resolve(true);
      });

      promise.then(function(successMessage) {
        _this.totalPrice();
        _this.orderTotal();
      }, function(errorMessage) {

      });
    },

    // count prise all booked tickets
    orderTotal() {
      this.total = 0
      if(Object.keys(this.total_price).length > 0)
      {
        this.total_price.forEach(function(value, key){

          this.total = (parseFloat(this.total) + parseFloat(value)).toFixed(2);

        }.bind(this))

        return this.total;
      }
      return 0;
    },

    // total booked tickets
    bookedTicketsTotal() {
      let  total = 0
      if(this.quantity.length > 0)
      {
        this.quantity.forEach(function(value, key){
          total = parseInt(total) + parseInt(value);

        }.bind(this))

        return total;
      }
      return 0;
    },

    defaultPaymentMethod() {
      // if not admin
      // total > 0
      if(this.is_admin <= 0 && this.bookedTicketsTotal() > 0)
        this.payment_method = 3;
    },

    loginFirst() {
      window.location.href = route('eventmie.login_first');
    },
    signupFirst() {
      window.location.href = route('eventmie.signup_first');
    },

    // get customers

    getCustomers(loading, search = null){
      var postUrl     = route('eventmie.get_customers');
      var _this       = this;
      axios.post(postUrl,{
        'search' :search,
      }).then(res => {

        var promise = new Promise(function(resolve, reject) {

          _this.options = res.data.customers;

          resolve(true);
        })

        promise
            .then(function(successMessage) {
              loading(false);
            }, function(errorMessage) {
              //error handler function is invoked
              console.log(errorMessage);
            })
      })
          .catch(error => {
            let serrors = Vue.helpers.axiosErrors(error);
            if (serrors.length) {
              this.serverValidate(serrors);
            }
          });
    },

    // v-select methods
    onSearch(search, loading) {
      loading(true);
      this.search(loading, search, this);
    },

    // v-select methods
    search: _.debounce((loading, search, vm) => {

      if(vm.validateEmail(search))
        vm.getCustomers(loading, search);
      else
        loading(false);

    }, 350),

    validateEmail(email) {
      const re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
      return re.test(email);
    }

  },
  watch: {
    quantity: function () {
      this.totalPrice();
      this.orderTotal();
      this.defaultPaymentMethod();
    },
    tickets: function() {
      this.setDefaultQuantity();
      this.totalPrice();
      this.orderTotal();
    },

    // active when customer search
    customer: function () {
      this.customer_id = this.customer != null ?  this.customer.id : null;
    },

  },

  mounted() {
    this.openModal = true;
    // this.setDefaultQuantity();
    this.defaultPaymentMethod();
  },
}
</script>