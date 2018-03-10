/*global Vue, axios, localStorage, EventSource*/

Vue.component('signin', {
  template: '#signin-template',
  data() {
    return {
      form: {
        username: '',
        email: ''
      }
    }
  },
  methods: {
    signIn() {
      axios.post('signIn.php', this.form).then(response => {
        //
        this.$emit('authenticated', {
          token: response.data.token,
          username: this.form.username
        })
      }).catch(error => {})
    }
  }
})

Vue.component('message', {
  template: '#message-template',
  props: ['msg'],
  data() {
    return {
      message: this.msg
    }
  }
})

//
new Vue({
  el: '#app',
  data() {
    return {
      user: Object.assign({ token: false }, JSON.parse(localStorage.getItem('user'))),
      connected: false,
      evtSource: false,
      messages: [],
      message: '',
      message_error: ''
    }
  },
  computed: {
    placeholderText: function() {
      return 'Hey ' + this.user.username + ', type your message here...'
    }
  },
  mounted: function() {
    if (this.user.token) {
      this.openConnection()
    }
  },
  methods: {
    signOut() {
      this.user.token = ''
      localStorage.removeItem('user')
    },
    setUser(user) {
      this.user = user
      localStorage.setItem('user', JSON.stringify(this.user));
      this.openConnection()
    },
    openConnection() {
      this.evtSource = new EventSource('sse.php', { withCredentials: true })
      this.evtSource.onopen = () => {
        this.connected = true
      }
      this.evtSource.onmessage = (e) => {
        let data = JSON.parse(e.data)
        for (var key in data) {
          if (data.hasOwnProperty(key)) {
            data[key]['side'] = (data[key].username == this.user.username ? 'left' : 'right')
            this.appendMessage(data[key])
          }
        }
      }
      this.evtSource.onerror = () => {
        this.connected = false
        this.messages.push({
          side: 'left',
          username: '',
          gravatar: '',
          body: 'Connection failed.'
        })
      }
    },
    closeConnection() {
      this.connected = false
      this.evtSource.close()
      this.messages.push({
        side: 'left',
        username: '',
        gravatar: '',
        body: 'Connection closed.'
      })
    },
    appendMessage(data) {
      this.messages.push(data)
    },
    checkMessage() {
      if (this.message === '') {
        this.message_error = 'You must enter a message.'
        return
      }
      else {
        this.message_error = ''
      }
    },
    postMessage() {
      if (this.message === '') {
        this.message_error = 'You must enter a message.'
        return
      }
      axios.post('postMessage.php', {
        token: this.user.token,
        message: this.message
      }).then(response => {
        this.message = ''
      }).catch(error => {})
    }
  },
  updated() {
    if (this.user.token) {
      var container = this.$el.querySelector("#messages")
      container.scrollTop = container.scrollHeight
    }
  }
});
