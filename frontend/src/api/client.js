import axios from 'axios'

const client = axios.create({
  baseURL: '/api',
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

client.interceptors.response.use(
  (response) => response.data,
  (error) => {
    if (error.response?.status === 401) {
      Promise.all([import('../router'), import('../stores/auth')]).then(
        ([routerModule, authModule]) => {
          const router = routerModule.default
          const { useAuthStore } = authModule
          const auth = useAuthStore()
          auth.user = null
          if (router.currentRoute.value.path !== '/login') {
            router.push('/login')
          }
        }
      )
    }
    return Promise.reject(error.response?.data || error)
  }
)

export function setCsrfToken(token) {
  client.defaults.headers.common['X-CSRF-Token'] = token
}

export default client
