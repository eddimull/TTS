import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

import axios from 'axios'

export default {
  namespaced: true,
  
  state: () => ({
    navigation: null,
    notifications: []
  }),
  
  mutations: {
    SET_NAVIGATION(state, navigation) {
      state.navigation = navigation
    },
    SET_NOTIFICATIONS(state, notifications) {
      state.notifications = notifications
    },
    MARK_NOTIFICATION_AS_READ(state, notificationId) {
      const notification = state.notifications.find(n => n.id === notificationId)
      if (notification) {
        notification.read_at = new Date()
      }
    },
    MARK_NOTIFICATIONS_AS_SEEN(state) {
      state.notifications.forEach(notification => {
        if (!notification.seen_at) {
          notification.seen_at = new Date()
        }
      })
    }
  },
  
  actions: {
    fetchNavigation({ commit }) {
        const page = usePage()
        const user = computed(() => page.props.auth.user)
        if (user.value) {
          const navigation = user.value.navigation
          commit('SET_NAVIGATION', navigation)
        } else {
          console.warn('Auth user or navigation not available')
          // Handle the case when auth or navigation is not available
          // For example, you might want to set an empty navigation array
          commit('SET_NAVIGATION', [])
        }
      },
      fetchNotifications({ commit }) {
        const page = usePage()
        const user = computed(() => page.props.auth.user)
        if (user.value && user.value.notifications) {
          const notifications = user.value.notifications
          commit('SET_NOTIFICATIONS', notifications)
        } else {
          console.warn('Notifications not available')
          // Handle the case when notifications are not available
          commit('SET_NOTIFICATIONS', [])
        }
      },
    
    async markAllNotificationsAsRead({ commit }) {
      try {
        await axios.post('/readAllNotifications')
        const response = await axios.get('/notifications')
        commit('SET_NOTIFICATIONS', response.data)
      } catch (error) {
        console.error('Error marking all notifications as read:', error)
      }
    },
    
    async markNotificationAsRead({ commit }, notificationId) {
      try {
        await axios.post(`/notification/${notificationId}`)
        commit('MARK_NOTIFICATION_AS_READ', notificationId)
      } catch (error) {
        console.error('Error marking notification as read:', error)
      }
    },
    
    async markNotificationsAsSeen({ commit }) {
      try {
        await axios.post('/seentIt')
        commit('MARK_NOTIFICATIONS_AS_SEEN')
      } catch (error) {
        console.error('Error marking notifications as seen:', error)
      }
    }
  }
}