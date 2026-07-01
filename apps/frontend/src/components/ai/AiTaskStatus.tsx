import { useEffect, useRef } from "react";
import { useQuery } from "@tanstack/react-query";
import { getAiTask } from "../../lib/api";

type AiTaskStatusProps = {
  taskId: number | null;
  onCompleted?: () => Promise<void> | void;
};

export function AiTaskStatus({ taskId, onCompleted }: AiTaskStatusProps) {
  const notifiedTaskId = useRef<number | null>(null);

  const taskQuery = useQuery({
    queryKey: ["ai-task", taskId],
    queryFn: () => getAiTask(taskId as number),
    enabled: Boolean(taskId),
    refetchInterval: (query) => {
      const status = query.state.data?.status;

      if (status === "completed" || status === "failed") {
        return false;
      }

      return 3000;
    },
  });

  const task = taskQuery.data;

  useEffect(() => {
    notifiedTaskId.current = null;
  }, [taskId]);

  useEffect(() => {
    if (task?.status !== "completed" || !taskId) {
      return;
    }

    if (notifiedTaskId.current === taskId) {
      return;
    }

    notifiedTaskId.current = taskId;
    void onCompleted?.();
  }, [onCompleted, task?.status, taskId]);

  if (!taskId) {
    return null;
  }

  if (taskQuery.isLoading || !task) {
    return (
      <div className="ai-task-status">
        <strong>Loading AI task...</strong>
      </div>
    );
  }

  return (
    <div className={`ai-task-status ${task.status}`}>
      <div>
        <strong>{task.title}</strong>
        <p>{task.message}</p>
      </div>

      <div className="ai-progress" aria-label={`${task.progress}% complete`}>
        <span style={{ width: `${task.progress}%` }} />
      </div>

      {task.error_message && <div className="form-error">{task.error_message}</div>}
    </div>
  );
}
