import { cn } from '../../../lib/utils'
import { ENVIRONMENTS, type Environment } from '../../../lib/constants'

interface Props {
  value: Environment
  onChange: (env: Environment) => void
}

export function EnvironmentPicker({ value, onChange }: Props) {
  return (
    <div className="flex flex-col gap-2">
      <div className="flex gap-1">
        {ENVIRONMENTS.map((env) => (
          <button
            key={env}
            type="button"
            onClick={() => onChange(env)}
            className={cn(
              'px-3 py-1 rounded-full text-xs font-medium transition-colors',
              value === env
                ? 'bg-blue-600 text-white'
                : 'bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-gray-200',
            )}
          >
            {env}
          </button>
        ))}
      </div>
      {value === 'production' && (
        <p className="text-amber-400 text-xs">⚠ Deploying to production</p>
      )}
    </div>
  )
}
