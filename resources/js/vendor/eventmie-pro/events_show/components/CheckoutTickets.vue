<template>
    <div>
     <div class="modal modal-mask" v-if="showModal">
       <div class="modal-dialog modal-container modal-lg">
         <div class="modal-content">
           <div class="modal-header">
             <button type="button" class="close" @click="close"><span aria-hidden="true">&times;</span></button>
             <div class="text-center">
               <h3 class="title ticket-selected-text mb-4">{{ trans('em.checkout') }}</h3>
             </div>
           </div>

             <form ref="form" @submit.prevent="validateForm" method="POST" >
                <input type="hidden" class="form-control" name="event_id" :value="tickets[0].event_id" >
                <input type="hidden" class="form-control" name="organiser_id" :value="organiser_id" >
                <input type="hidden" class="form-control" name="booking_date" :value="convert_date(moment(booking_date, 'dddd LL').format('dddd LL'))" >
                <input type="hidden" class="form-control" name="booking_end_date"
                      :value="(booking_end_date != null && typeof(booking_end_date) != 'undefined') ?
                                convert_date(moment(booking_end_date, 'dddd LL').locale('en').format('dddd LL')) : null"
                >
                <input type="hidden" class="form-control" name="start_time" :value="convert_time(moment(start_time,'HH:mm a'))" >
                <input type="hidden" class="form-control" name="end_time" :value="convert_time(moment(end_time,'HH:mm a'))" >
                <input type="hidden" class="form-control" name="merge_schedule" :value="event.merge_schedule" >
                <input type="hidden" name="customer_id" v-model="customer_id" v-validate="'required'" >


               <div class="form-group row ">
                 <label for="full_name" class="col-sm-2 my-0">Nom Complet</label>
                 <div class="col-sm-10">
                   <input type="text" class="form-control" id="full_name" name="full_name" v-model="full_name" v-validate="'required'">
                 </div>
               </div>
               <div id="phone_wrapper" class="form-group row">
                 <label for="phone" class="col-sm-2 my-0">Téléphone</label>
                 <div class="col-sm-10 pl-0 pr-0">
                    <div class="col-sm-2 " style="height: 68px;">
                      <vue-country-code
                          @onSelect="onSelect"
                                      :enableSearchField="true"
                                      :disabledFetchingCountry="false"
                                      :enabledCountryCode="true"
                                      :onlyCountries="['sn', 'fr', 'us','ml', 'gm', 'mr', 'gn']"
                                      :dropdownOptions="{ disabledDialCode: false }">
                      </vue-country-code>
                    </div>
                    <div class="col-sm-10 m-0">
                      <input type="tel" class="form-control" id="phone" name="phone" v-model="phone" v-validate="'required'">
                    </div>
                 </div>
               </div>

                <input type="hidden" name="country_code" v-model="country_code" v-validate="'required'" >
                <input type="hidden" name="country_dial_code" v-model="country_dial_code" v-validate="'required'" >

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
                       <strong :class="{'ticket-selected-text': bookedTicketsTotal() > 0 }">{{ this.total }} <small>{{currency}}</small></strong>
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
                   <button :class="{ 'disabled' : disable }"  :disabled="disable" type="button" class="btn lgx-btn btn-block" @click="bookTickets()"><i class="fas fa-cash-register"></i> {{ trans('em.checkout') }}</button>
                 </div>
                 <!-- <div class="col-xs-12">
                    <div class="btn-group btn-group-justified">
                      <button type="button" class="btn lgx-btn w-50" @click="signupFirst()"><i class="fas fa-user-plus"></i> {{ trans('em.register') }}</button>
                      <button type="button" class="btn lgx-btn lgx-btn-black w-50" @click="loginFirst()"><i class="fas fa-fingerprint"></i> {{ trans('em.login') }}</button>
                    </div>
                  </div>-->
               </div>

               <div>
                 <!-- Tickets -->
                <div class="col-md-12 d-none">
                  <p class="mb-0 lead lead-caption text-center">{{ trans('em.tickets') }}</p>

                  <ul class="list-group m-0">
                    <li class="list-group-item d-flex justify-content-between lh-condensed d-flex-wrap"
                        v-for="(item, index) in tickets"
                        :key = "index">
                      <input type="hidden" class="form-control" name="ticket_id[]" :value="item.id" >
                      <input type="hidden" class="form-control" name="ticket_title[]" :value="item.title" >


                      <div class="w-50">
                        <h6 class="my-0"><strong>{{ item.title }}</strong></h6>
                        <p class="mb-2">{{ item.price > 0 ? item.price : '0.00' }} <small>{{currency}}</small></p>

                        <!-- show tax only if quantity is set -->
                        <div class="event-tax" v-if="quantity[index] > 0 && item.price > 0 && item.taxes.length > 0">
                          <div v-for="(tax, index1) in item.taxes" :key ="index1">
                            <p>{{ tax.title }}
                              <small>{{ total_price[index] > 0 ? countTax(item.price, tax.rate, tax.rate_type, tax.net_price, quantity[index]) : 0 }}</small>
                            </p>
                          </div>
                        </div>
                      </div>

                      <div class="w-10 w-20-mobile">
                        <!-- Live stock alert  -->
                        <!-- if any booked tickets -->
                        <div v-if='typeof(booked_tickets[item.id+"-"+booked_date_server]) != "undefined"
                                              '>
                          <select class="form-control form-input-sm"
                                  name="quantity[]"
                                  v-model="quantity[index]"
                                  v-if='max_ticket_qty <= 100'
                          >
                            <option value="0" selected>0</option>
                            <option :key="ind"
                                    v-if="booked_tickets[item.id+'-'+booked_date_server].total_vacant <= max_ticket_qty"
                                    :value="itm" v-for=" (itm, ind) in booked_tickets[item.id+'-'+booked_date_server].total_vacant"
                            >{{itm }}</option>
                            <option v-else :value="itm" v-for=" (itm, ind) in (item.quantity > max_ticket_qty ? max_ticket_qty : item.quantity)"  :key="ind">{{itm }}</option>
                          </select>
                          <input v-else type="number" name="quantity[]"
                                v-model="quantity[index]" value="0" class="form-control form-input-sm"
                                min="0" :max="booked_tickets[item.id+'-'+booked_date_server].total_vacant < max_ticket_qty ? booked_tickets[item.id+'-'+booked_date_server].total_vacant : max_ticket_qty"
                          >
                          <!-- Show if vacant less than max_ticket_qty -->
                          <p class="text-info"
                            v-if="booked_tickets[item.id+'-'+booked_date_server].total_vacant < max_ticket_qty && booked_tickets[item.id+'-'+booked_date_server].total_vacant > 0">
                            <small><i class="fas fa-exclamation"></i> {{ trans('em.vacant') }}
                              {{ booked_tickets[item.id+'-'+booked_date_server].total_vacant }}</small>
                          </p>
                          <p class="text-danger"
                            v-if="booked_tickets[item.id+'-'+booked_date_server].total_vacant < max_ticket_qty && booked_tickets[item.id+'-'+booked_date_server].total_vacant <= 0">
                            <small><i class="fas fa-times-circle"></i>  {{ trans('em.vacant') }} 0</small>
                          </p>
                        </div>
                        <div v-else>
                          <select class="form-control form-input-sm"
                                  name="quantity[]"
                                  v-model="quantity[index]"
                                  v-if="max_ticket_qty <= 100"
                          >
                            <option value="0" selected>0</option>
                            <option :value="itm" v-for=" (itm, ind) in item.quantity > max_ticket_qty ? max_ticket_qty : item.quantity"  :key="ind">{{itm }}</option>
                          </select>
                          <input v-else type="number" name="quantity[]" v-model="quantity[index]" value="0" class="form-control form-input-sm" min="0" :max="item.quantity > max_ticket_qty ? max_ticket_qty : item.quantity">
                          <!-- Show if vacant less than max_ticket_qty -->
                          <p class="text-info"
                            v-if="item.quantity < max_ticket_qty && item.quantity > 0">
                            <small><i class="fas fa-exclamation"></i> {{ trans('em.vacant') }}
                              {{ item.quantity }}</small>
                          </p>
                          <p class="text-danger"
                            v-if="item.quantity <= 0">
                            <small><i class="fas fa-times-circle"></i>  {{ trans('em.vacant') }} 0</small>
                          </p>
                        </div>
                      </div>

                      <div class="w-30 text-right">
                        <strong>
                          {{ total_price[index] ? total_price[index] : '0.00' }}
                          <small>{{currency}}</small>
                        </strong>
                        <p v-if="quantity[index] > 0"><i class="fas fa-check-circle ticket-selected-text"></i></p>
                      </div>


                      <div class="break-flex">
                        <!-- hide/show below ticket description -->
                        <a class="pointer ticket-info-toggle" @click="ticket_info = !ticket_info">
                          <small v-if="ticket_info">{{ trans('em.hide_info') }}</small>
                          <small v-else>{{ trans('em.show_info') }}</small>
                        </a>
                        <p class="ticket-info" v-if="ticket_info">{{ item.description }}</p>
                      </div>

                    </li>
                  </ul>
                </div>
               </div>
             </form>
             <form action="https://api.paiementorangemoney.com" method="POST" ref="form_om"  >
               <input type="hidden" name="S2M_IDENTIFIANT" :value="om_config.identifiant">
               <input type="hidden" name="S2M_SITE" :value="om_config.site">
               <input type="hidden" name="S2M_TOTAL" :value="om_config.total">
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
import _ from "lodash";

export default {
  name: "CheckoutTickets",
  mixins:[
    mixinsFilters
  ],

  props : [
    'tickets',
    'max_ticket_qty',
    'event',
    'currency',
    'login_user_id',
    'is_admin',
    'is_organiser',
    'total',
    'quantity',
    'total_price',
    'is_customer',
    'is_paypal',
    'is_offline_payment_organizer',
    'is_offline_payment_customer',
    'booked_tickets',
    'organiser_id',
    // 'country_code',
  ],

  data() {
    return {
      ticket_info         : false,
      moment              : moment,
      // quantity            : [1],
      showModal           : true,
      price               : null,
      // total               : 0,
      full_name           : null,
      phone               : null,
      disable             : false,
      payment_method      : 'offline',
      country_code        : 'SN',
      country_dial_code   : '+221',
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

    }
  },

  computed: {
    // get global variables
    ...mapState( ['booking_date', 'start_time', 'end_time', 'booking_end_date', 'booked_date_server']),
  },

  methods: {
    onSelect({name, iso2, dialCode}) {
       console.log(name, iso2, dialCode);
       this.country_code = iso2;
       this.country_dial_code = dialCode;
       console.log(this.country_code);
       console.log(this.country_dial_code);
    },
    // update global variables
    ...mapMutations(['add', 'update']),

    // reset form and close modal
    close: function () {
      console.log("close close close close");
      this.price = null;
      this.quantity = [];
      this.total_price = [];

      this.add({
        booking_date: null,
        booked_date_server: null,
        booking_end_date: null,
        start_time: null,
        end_time: null,
      }) 

      this.openModal = true;

      this.showModal = false;
    },
  bookTickets() {
    // show loader
    this.showLoaderNotification(trans('em.processing'));

    // prepare form data for post request
    this.disable = true;

    let post_url = route('eventmie.bookings_book_tickets');
    let post_data = new FormData(this.$refs.form);

    // axios post request
    axios.post(post_url, post_data)
        .then(res => {

          if(res.data.status && res.data.message != ''  && typeof(res.data.message) != "undefined") {

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
      }
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
    console.log("sjdkfn");
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
    if(this.quantity != null && this.quantity.length > 0)
    {
      this.quantity.forEach(function(value, key){
        total = parseInt(total) + parseInt(value);

      }.bind(this))

      return total;
    }
    // return this.total
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
  // quantity: function () {
  //   this.totalPrice();
  //   this.orderTotal();
  //   this.defaultPaymentMethod();
  // },

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
    this.showModal = true;
    this.defaultPaymentMethod();
  },
}
</script>