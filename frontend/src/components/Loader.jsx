export default function Loader({ full = false }) {
  return (
    <div className={full ? 'loader loader-full' : 'loader'}>
      <span className="spinner" />
    </div>
  )
}
