import { defineStore } from 'pinia'
import axios from 'axios'

export const useUserStore = defineStore('user', {
  state: () => ({
    avatar: null
  }),
  
  actions: {
    async fetchUserData() {
      try {
        const response = await axios.get('/api/user/getuser')
        if (response.data.success) {
          this.avatar = response.data.user.avatar
        }
      } catch (error) {
        console.error('Error fetching user data:', error)
      }
    }
  }
})